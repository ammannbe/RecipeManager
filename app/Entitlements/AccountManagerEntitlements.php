<?php

namespace App\Entitlements;

use App\Households\AccountManagerClient;
use App\Households\AccountManagerUnavailable;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\UserDeletion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stores the entitlements snapshots of the AccountManager (contract: AccountManager docs/ENTITLEMENTS-API.md,
 * sections 5 and 6): every webhook and API answer replaces the stored state of the user. Webhooks
 * apply only newer versions, API answers also the same version; 404 of the API sets "none" and keeps
 * the version. Events of unknown subjects are ignored, the OIDC sign-in fetches the snapshot.
 *
 * @phpstan-import-type EntitlementSnapshot from AccountManagerClient
 */
class AccountManagerEntitlements
{
    public const CHANGED = 'entitlements.changed';

    public const USER_DELETED = 'user.deleted';

    public function __construct(private readonly AccountManagerClient $client) {}

    /**
     * Webhook entitlements.changed. False if the subject is unknown or the version is stale.
     *
     * @param  EntitlementSnapshot  $snapshot
     */
    public function handleChanged(array $snapshot): bool
    {
        $user = User::query()->where('oidc_sub', $snapshot['sub'])->first();

        return $user !== null && $this->apply($user, $snapshot);
    }

    /**
     * Webhook user.deleted: the user and their private contents are deleted (unknown subjects: nothing).
     */
    public function handleDeleted(string $sub): void
    {
        $user = User::withTrashed()->where('oidc_sub', $sub)->first();

        if ($user !== null) {
            app(UserDeletion::class)->delete($user);
        }
    }

    /**
     * Replaces the stored state; false when it is stale.
     *
     * @param  EntitlementSnapshot  $snapshot
     */
    public function apply(User $user, array $snapshot, bool $authoritative = false): bool
    {
        try {
            return $this->applyOnce($user, $snapshot, $authoritative);
        } catch (UniqueConstraintViolationException) {
            // Two deliveries created the row at once: apply on top of the other one.
            return $this->applyOnce($user, $snapshot, $authoritative);
        }
    }

    /**
     * Fetches and stores the user's snapshot (sign-in and daily sync).
     *
     * @throws AccountManagerUnavailable the stored state stays
     */
    public function sync(User $user): void
    {
        if ($user->oidc_sub === null) {
            return;
        }

        $snapshot = $this->client->entitlements($user->oidc_sub);

        if ($snapshot !== null) {
            $this->apply($user, $snapshot, authoritative: true);

            return;
        }

        // 404: no access (anymore). Nothing is deleted, the version stays.
        $entitlement = UserEntitlement::query()->firstOrNew(['user_id' => $user->getKey()]);
        $entitlement->fill([
            'status' => Entitlements::NONE,
            'admin' => false,
            'features' => [],
            'version' => $entitlement->version ?? 0,
            'synced_at' => now(),
        ])->save();

        $user->unsetRelation('entitlement');
    }

    /**
     * OIDC sign-in and linking: short timeout, the sign-in never fails because of the AccountManager.
     */
    public function syncOnSignIn(User $user): void
    {
        if (! Entitlements::syncEnabled()) {
            return;
        }

        try {
            (new self($this->client->withTimeout(5)))->sync($user);
        } catch (AccountManagerUnavailable $exception) {
            self::logUnavailable($exception, 'syncing the entitlements on sign-in');
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function logUnavailable(AccountManagerUnavailable $exception, string $context): void
    {
        Log::warning("Entitlements: {$context} failed, keeping the last state.", ['exception' => $exception->getMessage()]);
    }

    /**
     * @param  EntitlementSnapshot  $snapshot
     */
    private function applyOnce(User $user, array $snapshot, bool $authoritative): bool
    {
        $applied = DB::transaction(function () use ($user, $snapshot, $authoritative): bool {
            $entitlement = UserEntitlement::query()->where('user_id', $user->getKey())->lockForUpdate()->first();

            if ($entitlement !== null) {
                $stale = $authoritative
                    ? $snapshot['version'] < $entitlement->version
                    : $snapshot['version'] <= $entitlement->version;

                if ($stale) {
                    if ($authoritative) {
                        $entitlement->forceFill(['synced_at' => now()])->save();
                    }

                    return false;
                }
            }

            $entitlement ??= new UserEntitlement(['user_id' => $user->getKey()]);
            $entitlement->fill([
                'status' => $snapshot['status'],
                'admin' => $snapshot['admin'],
                'features' => $snapshot['features'],
                'version' => $snapshot['version'],
                'synced_at' => $authoritative ? now() : $entitlement->synced_at,
            ])->save();

            return true;
        });

        $user->unsetRelation('entitlement');

        return $applied;
    }
}
