<?php

namespace App\Jobs;

use App\Announcements\AnnouncementSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One batch of push messages or e-mails of an announcement (AnnouncementSender). A retry skips the users that
 * were handled already (announcement_deliveries).
 */
class SendAnnouncementBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public string $channel, public int $announcementId, public array $userIds) {}

    public function handle(AnnouncementSender $sender): void
    {
        $sender->sendBatch($this->channel, $this->announcementId, $this->userIds);
    }
}
