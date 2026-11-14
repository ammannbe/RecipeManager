<?php

namespace App\Jobs;

use App\Announcements\AccountManagerAnnouncements;
use App\Announcements\Announcements;
use App\Households\AccountManagerUnavailable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Started by the webhook announcements.changed (the event is only a trigger): fetches the changes since the last
 * server_time. Retried while the AccountManager is down; the 15-minute sync heals the rest.
 */
class SyncAccountManagerAnnouncements implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function handle(AccountManagerAnnouncements $announcements): void
    {
        if (! Announcements::enabled()) {
            return;
        }

        try {
            $announcements->sync();
        } catch (AccountManagerUnavailable $exception) {
            AccountManagerAnnouncements::logUnavailable($exception, 'syncing after a webhook');

            if ($this->attempts() < $this->tries) {
                $this->release(60 * $this->attempts());
            }
        }
    }
}
