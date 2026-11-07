<?php

namespace App\Households;

use App\Enums\HouseholdRole;
use App\Jobs\ReportKontoShares;
use App\Models\CookbookHouseholdShare;
use App\Models\Household;
use App\Models\HouseholdPendingMember;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors the households of the AccountManager (HOUSEHOLDS_DRIVER=konto, contract: AccountManager
 * docs/HOUSEHOLDS-API.md, section 5). Every webhook and API answer carries the full snapshot of a
 * household: the local copy is set to it (name, members by oidc_sub, roles), older versions are
 * ignored. Access to shared cookbooks is resolved through the membership at runtime
 * (Cookbook::grants()), so it follows the members right away.
 *
 * @phpstan-import-type Snapshot from KontoClient
 */
class KontoHouseholds
{
    public const DELETED = 'household.deleted';

    public function __construct(private readonly KontoClient $client) {}

    public static function enabled(): bool
    {
        return config('households.driver') === 'konto' && KontoClient::configured();
    }

    /**
     * Households page of the AccountManager's portal, null with the local driver.
     */
    public static function portalUrl(): ?string
    {
        $url = config('households.konto.url');

        return config('households.driver') === 'konto' && filled($url) ? rtrim((string) $url, '/').'/households' : null;
    }

    /**
     * OIDC sign-in and linking: attaches pending memberships and syncs the user's households with a
     * short timeout. The sign-in never fails because of it (the daily sync catches up).
     */
    public function syncOnSignIn(User $user): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            (new self($this->client->withTimeout(5)))->syncUser($user);
        } catch (KontoUnavailable $exception) {
            self::logUnavailable($exception, 'syncing the households on sign-in');
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Applies a webhook event (any type, the snapshot counts). Events of unknown households are
     * confirmed through the API, so stale events after a deletion create nothing.
     *
     * @param  array{uuid: string, name: string, version: int, members: list<array{sub: string, role: string}>, deleted?: bool}  $snapshot
     *
     * @throws KontoUnavailable
     */
    public function handleEvent(string $type, array $snapshot): void
    {
        if ($type === self::DELETED || ! empty($snapshot['deleted'])) {
            $this->delete($snapshot['uuid']);

            return;
        }

        if (! Household::query()->where('konto_household_uuid', $snapshot['uuid'])->exists()) {
            $this->refresh($snapshot['uuid']);

            return;
        }

        unset($snapshot['deleted']);

        $this->apply($snapshot);
    }

    /**
     * Fetches the household and applies it; 404 deletes the local copy.
     *
     * @throws KontoUnavailable
     */
    public function refresh(string $uuid): ?Household
    {
        $snapshot = $this->client->household($uuid);

        if ($snapshot === null) {
            $this->delete($uuid);

            return null;
        }

        return $this->apply($snapshot, authoritative: true);
    }

    /**
     * Sets the local copy to the snapshot. Webhooks apply only newer versions, API answers
     * ($authoritative) also the same version.
     *
     * @param  Snapshot  $snapshot
     */
    public function apply(array $snapshot, bool $authoritative = false, ?Household $household = null): Household
    {
        try {
            return $this->applyOnce($snapshot, $authoritative, $household);
        } catch (UniqueConstraintViolationException) {
            // Two deliveries created the same household at once: the other one won, apply on top.
            return $this->applyOnce($snapshot, $authoritative, null);
        }
    }

    /**
     * Memberships, pending members and cookbook shares go with it (FK cascades); the cookbooks
     * and recipes stay with their owners.
     */
    public function delete(string $uuid): void
    {
        Household::query()->where('konto_household_uuid', $uuid)->first()?->delete();
    }

    /**
     * Login and daily sync: all households of the user (3.2). Mirrored households of the user that
     * are missing were left or deleted: they are checked one by one (3.3). Uuids applied are added
     * to $checked, so the daily sync fetches every household once.
     *
     * @param  array<array-key, true>  $checked
     *
     * @throws KontoUnavailable the user's households could not be fetched (the state stays)
     */
    public function syncUser(User $user, array &$checked = []): void
    {
        if ($user->oidc_sub === null) {
            return;
        }

        $this->attachPending($user);

        $seen = [];

        foreach ($this->client->households($user->oidc_sub) as $snapshot) {
            $seen[$snapshot['uuid']] = true;

            if (! isset($checked[$snapshot['uuid']])) {
                $this->apply($snapshot, authoritative: true);
                $checked[$snapshot['uuid']] = true;
            }
        }

        $missing = $user->households()
            ->whereNotNull('konto_household_uuid')
            ->pluck('konto_household_uuid')
            ->reject(fn (string $uuid): bool => isset($seen[$uuid]) || isset($checked[$uuid]));

        foreach ($missing as $uuid) {
            $this->refresh($uuid);
            $checked[$uuid] = true;
        }
    }

    /**
     * The user signed in with OIDC (or linked the sign-in): memberships the AccountManager reported
     * for their subject before become real ones.
     */
    public function attachPending(User $user): void
    {
        if ($user->oidc_sub === null) {
            return;
        }

        HouseholdPendingMember::query()
            ->where('oidc_sub', $user->oidc_sub)
            ->with('household')
            ->get()
            ->each(function (HouseholdPendingMember $pending) use ($user): void {
                DB::transaction(function () use ($pending, $user): void {
                    $household = $pending->household;

                    if (! $household->hasMember($user)) {
                        $household->users()->attach($user->getKey(), [
                            'role' => $pending->role->value,
                            'joined_at' => now(),
                        ]);
                    }

                    $pending->delete();
                });
            });
    }

    /**
     * The user unlinked the sign-in: they lose the mirrored households (kept as pending, in case
     * the sign-in is linked again) and their own cookbooks are no longer shared with them.
     */
    public function detachUser(User $user): void
    {
        $user->households()
            ->whereNotNull('konto_household_uuid')
            ->get()
            ->each(function (Household $household) use ($user): void {
                DB::transaction(function () use ($household, $user): void {
                    if ($user->oidc_sub !== null) {
                        $household->pendingMembers()->updateOrCreate(
                            ['oidc_sub' => $user->oidc_sub],
                            ['role' => ($household->roleOf($user) ?? HouseholdRole::Member)->value],
                        );
                    }

                    $household->detachMembers([$user->getKey()]);
                });
            });
    }

    /**
     * Switching from local households: the AccountManager confirmed the proposal of $local. Its
     * cookbook shares stay; if the household is mirrored already (the event came first), the shares
     * move there (grants add up) and the local household is dropped. Name and members come from
     * the snapshot.
     *
     * @param  Snapshot  $snapshot
     */
    public function adopt(Household $local, array $snapshot): Household
    {
        return DB::transaction(function () use ($local, $snapshot): Household {
            $target = Household::query()->where('konto_household_uuid', $snapshot['uuid'])->first();

            if ($target === null || $target->is($local)) {
                $local->forceFill([
                    'konto_household_uuid' => $snapshot['uuid'],
                    'konto_version' => null,
                    'konto_import_status' => null,
                ])->save();

                $target = $local;
            } else {
                $this->moveShares($local, $target);
                $local->delete();
            }

            $target = $this->apply($snapshot, authoritative: true, household: $target);
            $this->reportShares($target);

            return $target;
        });
    }

    /**
     * Queues the report of the shares (3.4, overview in the AccountManager, failures are harmless).
     */
    public function reportShares(Household $household): void
    {
        ReportKontoShares::dispatchFor($household);
    }

    /**
     * Logs a failed call; the app keeps working with the last mirrored state.
     */
    public static function logUnavailable(KontoUnavailable $exception, string $context): void
    {
        Log::warning("Households: {$context} failed, keeping the last state.", ['exception' => $exception->getMessage()]);
    }

    /**
     * @param  Snapshot  $snapshot
     */
    private function applyOnce(array $snapshot, bool $authoritative, ?Household $household): Household
    {
        return DB::transaction(function () use ($snapshot, $authoritative, $household): Household {
            $household = $household
                ? Household::query()->whereKey($household->getKey())->lockForUpdate()->firstOrFail()
                : Household::query()->where('konto_household_uuid', $snapshot['uuid'])->lockForUpdate()->first();

            if ($household?->konto_version !== null) {
                $stale = $authoritative
                    ? $snapshot['version'] < $household->konto_version
                    : $snapshot['version'] <= $household->konto_version;

                if ($stale) {
                    return $household;
                }
            }

            $household ??= new Household(['konto_household_uuid' => $snapshot['uuid']]);
            $household->name = mb_substr($snapshot['name'], 0, 255);
            $household->konto_version = $snapshot['version'];
            $household->save();

            $this->syncMembers($household, $snapshot['members']);

            return $household;
        });
    }

    /**
     * Members by oidc_sub: unknown subjects are kept as pending members. Removed members lose access
     * and their own cookbooks are no longer shared with the household, like after leaving a local one.
     *
     * @param  list<array{sub: string, role: string}>  $members
     */
    private function syncMembers(Household $household, array $members): void
    {
        $roles = [];

        foreach ($members as $member) {
            $roles[$member['sub']] = (HouseholdRole::tryFrom($member['role']) ?? HouseholdRole::Member)->value;
        }

        $users = User::query()->whereIn('oidc_sub', array_map('strval', array_keys($roles)))->get()->keyBy('id');
        $current = $household->users()->get()->keyBy('id');

        foreach ($users as $user) {
            $role = $roles[$user->oidc_sub];
            $membership = $current->get($user->getKey())?->getRelationValue('pivot');

            if ($membership === null) {
                $household->users()->attach($user->getKey(), ['role' => $role, 'joined_at' => now()]);
            } elseif ($membership->role !== HouseholdRole::from($role)) {
                $household->users()->updateExistingPivot($user->getKey(), ['role' => $role]);
            }
        }

        $removed = $current->keys()->diff($users->keys())->values()->all();

        if ($removed !== []) {
            $household->detachMembers($removed);
        }

        $pending = array_diff_key($roles, array_flip($users->pluck('oidc_sub')->all()));

        $household->pendingMembers()->whereNotIn('oidc_sub', array_map('strval', array_keys($pending)))->delete();

        foreach ($pending as $sub => $role) {
            $household->pendingMembers()->updateOrCreate(['oidc_sub' => (string) $sub], ['role' => $role]);
        }
    }

    /**
     * Moves the cookbook shares of $from to $to; a cookbook shared with both keeps the sum of the grants.
     */
    private function moveShares(Household $from, Household $to): void
    {
        CookbookHouseholdShare::query()->where('household_id', $from->getKey())->get()
            ->each(function (CookbookHouseholdShare $share) use ($to): void {
                $existing = CookbookHouseholdShare::query()
                    ->where('household_id', $to->getKey())
                    ->where('cookbook_id', $share->cookbook_id)
                    ->first();

                if ($existing === null) {
                    $share->forceFill(['household_id' => $to->getKey()])->save();

                    return;
                }

                foreach (CookbookHouseholdShare::GRANTS as $grant) {
                    $existing->{$grant} = $existing->{$grant} || $share->{$grant};
                }

                $existing->save();
            });
    }
}
