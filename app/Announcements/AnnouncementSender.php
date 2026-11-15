<?php

namespace App\Announcements;

use App\Households\AccountManagerClient;
use App\Households\AccountManagerUnavailable;
use App\Jobs\SendAnnouncementBatch;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPush;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\WebPushChannel;
use Throwable;

/**
 * Push and e-mail of announcements (ANNOUNCEMENTS-API.md 5): once per announcement and channel, when the app first
 * sees it published. dispatchPending() claims the channel atomically (announcements.push_dispatched_at /
 * email_dispatched_at) and queues the recipients in small batches; every batch re-checks the consent and records each
 * user in announcement_deliveries first (unique), so overlapping runs and retried jobs never send twice. Each batch
 * reports its numbers to the AccountManager (3.3, batch id = channel and user id range, so a retry replaces them).
 *
 * Recipients: push = users with a push subscription (enabled on a device in the profile); e-mail = verified addresses
 * with "News by e-mail" = all, for maintenance notices also = maintenance (the default; none = unsubscribed). Users who
 * registered after the announcement was published only get maintenance ones. Only announcements published at most
 * send_max_age_days ago are sent (ANNOUNCEMENTS_SEND_MAX_AGE_DAYS, same in all apps).
 */
class AnnouncementSender
{
    public function __construct(private AccountManagerClient $client) {}

    public function dispatchPending(): void
    {
        if (! Announcements::enabled()) {
            return;
        }

        $columns = [Announcement::EMAIL => 'email_dispatched_at'];

        // Without VAPID keys nothing is claimed: announcements published within the window go out once push is set up.
        if (Announcements::pushEnabled()) {
            $columns[Announcement::PUSH] = 'push_dispatched_at';
        }

        foreach ($columns as $channel => $column) {
            Announcement::query()
                ->live()
                ->whereNull($column)
                ->whereJsonContains('channels', $channel)
                ->get()
                ->each(fn (Announcement $announcement) => $this->dispatch($announcement, $channel, $column));
        }
    }

    /**
     * Users who may get the announcement on the channel (at the time of the call).
     *
     * @return Builder<User>
     */
    public static function recipients(Announcement $announcement, string $channel): Builder
    {
        $query = User::query()->without('author');

        if (! $announcement->isMaintenance()) {
            $query->where('created_at', '<=', $announcement->publish_at);
        }

        if ($channel === Announcement::PUSH) {
            return $query->whereHas('pushSubscriptions');
        }

        return $query
            ->whereNotNull('email_verified_at')
            ->whereIn('announcement_emails', Announcements::emailSettingsFor($announcement));
    }

    /**
     * Sends one queued batch (SendAnnouncementBatch).
     *
     * @param  list<int>  $userIds
     */
    public function sendBatch(string $channel, int $announcementId, array $userIds): void
    {
        $announcement = Announcement::query()->live()->find($announcementId);

        // Withdrawn or expired in the meantime: nothing more is sent.
        if ($announcement === null || ! Announcements::enabled() || ($channel === Announcement::PUSH && ! Announcements::pushEnabled())) {
            return;
        }

        /** @var Collection<int, User> $users */
        $users = self::recipients($announcement, $channel)->whereKey($userIds)->get();

        foreach ($users as $user) {
            $claimed = DB::table('announcement_deliveries')->insertOrIgnore([
                'announcement_id' => $announcement->getKey(),
                'user_id' => $user->getKey(),
                'channel' => $channel,
                'status' => 'pending',
                'created_at' => now(),
            ]) > 0;

            if (! $claimed) {
                continue;
            }

            $sent = $channel === Announcement::EMAIL ? $this->mail($announcement, $user) : $this->push($announcement, $user);

            DB::table('announcement_deliveries')
                ->where(['announcement_id' => $announcement->getKey(), 'user_id' => $user->getKey(), 'channel' => $channel])
                ->update(['status' => $sent ? 'sent' : 'failed']);
        }

        $this->report($announcement, $channel, $userIds);
    }

    private function dispatch(Announcement $announcement, string $channel, string $column): void
    {
        $claimed = Announcement::query()->whereKey($announcement->getKey())->whereNull($column)->update([$column => now()]) === 1;

        if (! $claimed) {
            return;
        }

        $maxAge = max(1, (int) config('announcements.send_max_age_days'));

        if ($announcement->publish_at->lt(now()->subDays($maxAge))) {
            Log::info("Announcements: {$announcement->account_manager_uuid} was published more than {$maxAge} days ago, {$channel} is not sent.");

            return;
        }

        $batch = 0;
        $delay = (int) config('announcements.batch_delay');

        self::recipients($announcement, $channel)
            ->select('users.id')
            ->chunkById(max(1, (int) config('announcements.batch_size')), function (Collection $users) use ($announcement, $channel, $delay, &$batch): void {
                /** @var list<int> $ids */
                $ids = array_map('intval', $users->modelKeys());

                SendAnnouncementBatch::dispatch($channel, $announcement->getKey(), $ids)
                    ->delay($channel === Announcement::EMAIL && $batch > 0 ? now()->addSeconds($batch * $delay) : null);

                $batch++;
            }, 'users.id', 'id');
    }

    private function mail(Announcement $announcement, User $user): bool
    {
        try {
            Mail::to($user)->locale($user->locale ?: config('app.locale'))->send(new AnnouncementMail($announcement, $user));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function push(Announcement $announcement, User $user): bool
    {
        try {
            $reports = app(WebPushChannel::class)->send($user, new AnnouncementPush($announcement));

            return collect($reports)->contains(fn (MessageSentReport $report): bool => $report->isSuccess());
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @param  list<int>  $userIds
     */
    private function report(Announcement $announcement, string $channel, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $counts = DB::table('announcement_deliveries')
            ->where('announcement_id', $announcement->getKey())
            ->where('channel', $channel)
            ->whereIn('user_id', $userIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) ($counts['sent'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);

        if ($sent + $failed === 0) {
            return;
        }

        try {
            $this->client->reportDelivery($announcement->account_manager_uuid, $channel, $sent, $failed, $channel.'-'.min($userIds).'-'.max($userIds));
        } catch (AccountManagerUnavailable $exception) {
            // Only an overview in the AccountManager.
            AccountManagerAnnouncements::logUnavailable($exception, 'reporting the deliveries');
        }
    }
}
