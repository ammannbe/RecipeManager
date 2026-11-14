<?php

namespace App\Console\Commands;

use App\Announcements\AccountManagerAnnouncements;
use App\Announcements\Announcements;
use App\Households\AccountManagerUnavailable;
use Illuminate\Console\Command;

/**
 * ANNOUNCEMENTS_DRIVER=account-manager: fetches the announcements changed since the last server_time (every 15
 * minutes) or all of them (--full, daily; also the first run). Then push and e-mail of new ones are queued. If the
 * AccountManager is not reachable, the last state stays and the next run catches up.
 */
class SyncAnnouncements extends Command
{
    protected $signature = 'account-manager:sync-announcements {--full : Fetch all published announcements (withdraws missing copies)}';

    protected $description = 'Mirror the announcements of the AccountManager';

    public function handle(AccountManagerAnnouncements $announcements): int
    {
        if (! Announcements::enabled()) {
            $this->info('Announcements are not synced (ANNOUNCEMENTS_DRIVER=account-manager with ACCOUNT_MANAGER_URL, ACCOUNT_MANAGER_SERVICE_KEY and ACCOUNT_MANAGER_SECRET).');

            return self::SUCCESS;
        }

        try {
            $result = $announcements->sync((bool) $this->option('full'));
        } catch (AccountManagerUnavailable $exception) {
            AccountManagerAnnouncements::logUnavailable($exception, 'account-manager:sync-announcements');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($result['full'] ? 'Full sync: ' : '')."{$result['updated']} announcement(s) updated, {$result['withdrawn']} withdrawn.");

        return self::SUCCESS;
    }
}
