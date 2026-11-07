<?php

namespace App\Console\Commands;

use App\Households\KontoClient;
use App\Households\KontoHouseholds;
use App\Households\KontoUnavailable;
use App\Models\Household;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Switching from HOUSEHOLDS_DRIVER=local to konto: proposes the local households to the
 * AccountManager (members with oidc_sub only), where an admin creates or merges them, then maps the
 * confirmed ones (konto_household_uuid, name and members from the AccountManager, cookbook shares
 * stay). Proposing again is allowed (idempotent by local id). --check only polls the state (hourly).
 *
 * @phpstan-import-type ImportState from KontoClient
 */
class ImportHouseholds extends Command
{
    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    protected $signature = 'households:import {--check : Only fetch the state of the proposals}';

    protected $description = 'Propose the local households to the AccountManager and map the confirmed ones';

    public function handle(KontoClient $client, KontoHouseholds $households): int
    {
        if (! KontoHouseholds::enabled()) {
            $this->error('Set HOUSEHOLDS_DRIVER=konto, KONTO_URL, KONTO_SERVICE_KEY and KONTO_SECRET first.');

            return self::FAILURE;
        }

        $check = (bool) $this->option('check');

        if ($check && ! Household::query()->whereNull('konto_household_uuid')->where('konto_import_status', self::PENDING)->exists()) {
            return self::SUCCESS;
        }

        try {
            if (! $check) {
                $this->propose($client);
            }

            $this->apply($client->importStatus(), $households);
        } catch (KontoUnavailable $exception) {
            KontoHouseholds::logUnavailable($exception, 'households:import');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function propose(KontoClient $client): void
    {
        $local = Household::query()
            ->whereNull('konto_household_uuid')
            ->with(['users' => fn ($query) => $query->orderByPivot('joined_at')->orderBy('users.id')])
            ->orderBy('id')
            ->get();

        $proposals = $local->map(fn (Household $household): array => [
            'local_id' => (string) $household->getKey(),
            'name' => mb_substr($household->name, 0, 100),
            'members' => array_values($household->users
                ->whereNotNull('oidc_sub')
                ->take(100)
                ->map(fn (User $user): array => [
                    'sub' => (string) $user->oidc_sub,
                    'role' => (string) $user->getRelationValue('pivot')->role->value,
                ])
                ->all()),
        ])->filter(fn (array $proposal): bool => $proposal['members'] !== [])->values();

        $skipped = $local->count() - $proposals->count();

        if ($skipped > 0) {
            $this->warn("{$skipped} household(s) have no member with a linked sign-in (oidc_sub) and stay local.");
        }

        $withoutLogin = $local->sum(fn (Household $household): int => $household->users->whereNull('oidc_sub')->count());

        if ($withoutLogin > 0) {
            $this->warn("{$withoutLogin} membership(s) without a linked sign-in are not proposed: these members lose the household access once it is confirmed.");
        }

        foreach ($proposals->chunk(500) as $chunk) {
            foreach ($client->propose(array_values($chunk->all())) as $state) {
                Household::query()
                    ->whereKey((int) $state['local_id'])
                    ->whereNull('konto_household_uuid')
                    ->update(['konto_import_status' => $state['status'] === self::REJECTED ? self::REJECTED : self::PENDING]);
            }
        }

        $this->info("Proposed {$proposals->count()} household(s) to the AccountManager.");
    }

    /**
     * @param  list<ImportState>  $states
     */
    private function apply(array $states, KontoHouseholds $households): void
    {
        $counts = ['confirmed' => 0, self::PENDING => 0, self::REJECTED => 0];

        foreach ($states as $state) {
            $local = ctype_digit($state['local_id'])
                ? Household::query()->whereKey((int) $state['local_id'])->whereNull('konto_household_uuid')->first()
                : null;

            if ($local === null) {
                continue;
            }

            if ($state['status'] === 'confirmed' && $state['household'] !== null) {
                $households->adopt($local, $state['household']);
                $counts['confirmed']++;
            } elseif (in_array($state['status'], [self::PENDING, self::REJECTED], true)) {
                $local->forceFill(['konto_import_status' => $state['status']])->save();
                $counts[$state['status']]++;
            }
        }

        $this->info("Confirmed: {$counts['confirmed']}, pending: {$counts[self::PENDING]}, rejected: {$counts[self::REJECTED]}.");
    }
}
