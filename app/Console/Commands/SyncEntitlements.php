<?php

namespace App\Console\Commands;

use App\Entitlements\AccountManagerEntitlements;
use App\Entitlements\Entitlements;
use App\Households\AccountManagerUnavailable;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Daily fallback for lost webhooks (ENTITLEMENTS_DRIVER or AUTH_ADMIN_SOURCE=account-manager): the snapshot
 * of every user with oidc_sub. If the AccountManager is not reachable, the last state stays.
 */
class SyncEntitlements extends Command
{
    protected $signature = 'entitlements:sync';

    protected $description = 'Fetch the entitlements of all linked users from the AccountManager';

    public function handle(AccountManagerEntitlements $entitlements): int
    {
        if (! Entitlements::syncEnabled()) {
            $this->info('Entitlements are not synced (ENTITLEMENTS_DRIVER or AUTH_ADMIN_SOURCE=account-manager with ACCOUNT_MANAGER_URL, ACCOUNT_MANAGER_SERVICE_KEY and ACCOUNT_MANAGER_SECRET).');

            return self::SUCCESS;
        }

        $users = 0;

        try {
            User::query()->whereNotNull('oidc_sub')->lazyById()->each(function (User $user) use ($entitlements, &$users): void {
                $entitlements->sync($user);
                $users++;
            });
        } catch (AccountManagerUnavailable $exception) {
            AccountManagerEntitlements::logUnavailable($exception, 'entitlements:sync');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced the entitlements of {$users} user(s).");

        return self::SUCCESS;
    }
}
