<?php

namespace App\Console\Commands;

use App\Households\AccountManagerClient;
use App\Households\AccountManagerUnavailable;
use App\Households\AccountManagerUsers;
use Illuminate\Console\Command;

/**
 * Full sync of this app's users to the AccountManager (ACCOUNT_MANAGER_REPORT_USERS, daily): repairs lost
 * single reports; users missing in the list are removed there.
 */
class ReportAccountManagerUsers extends Command
{
    protected $signature = 'account-manager:report-users';

    protected $description = 'Report all users of this app to the AccountManager (full sync)';

    public function handle(AccountManagerUsers $users, AccountManagerClient $client): int
    {
        if (! AccountManagerUsers::enabled()) {
            $this->info('Users are not reported (ACCOUNT_MANAGER_REPORT_USERS with ACCOUNT_MANAGER_URL, ACCOUNT_MANAGER_SERVICE_KEY and ACCOUNT_MANAGER_SECRET).');

            return self::SUCCESS;
        }

        try {
            $result = $users->sync($client);
        } catch (AccountManagerUnavailable $exception) {
            AccountManagerUsers::logUnavailable($exception, 'account-manager:report-users');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Reported users: {$result['upserted']} upserted, {$result['removed']} removed in the AccountManager.");

        return self::SUCCESS;
    }
}
