<?php

namespace App\Announcements;

use App\Households\AccountManagerClient;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Announcements in the panel (ANNOUNCEMENTS_DRIVER=account-manager): the bell with the unread counter and the
 * "News" page (channel in_app), the banner (channel banner) and the read/dismissed state per user.
 */
class Announcements
{
    /**
     * E-mail setting of the users (users.announcement_emails), same in Shoppinglist and Chorelist: all news,
     * only maintenance notices (default) or none (also set by the unsubscribe link).
     */
    public const EMAILS_ALL = 'all';

    public const EMAILS_MAINTENANCE = 'maintenance';

    public const EMAILS_NONE = 'none';

    public const EMAIL_SETTINGS = [self::EMAILS_ALL, self::EMAILS_MAINTENANCE, self::EMAILS_NONE];

    /**
     * ANNOUNCEMENTS_DRIVER: "account-manager"; "none" (default), "local" and empty show nothing.
     */
    public static function enabled(): bool
    {
        return config('announcements.driver') === 'account-manager' && AccountManagerClient::configured();
    }

    /**
     * Settings that get the announcement by e-mail: maintenance notices with "all" and "maintenance", the rest only
     * with "all".
     *
     * @return list<string>
     */
    public static function emailSettingsFor(Announcement $announcement): array
    {
        return $announcement->isMaintenance() ? [self::EMAILS_ALL, self::EMAILS_MAINTENANCE] : [self::EMAILS_ALL];
    }

    /**
     * Web Push needs the VAPID keys (php artisan webpush:vapid).
     */
    public static function pushEnabled(): bool
    {
        return self::enabled() && filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    /**
     * Announcements of the "News" page, newest first.
     *
     * @return Collection<int, Announcement>
     */
    public static function news(User $user): Collection
    {
        if (! self::enabled()) {
            return new Collection;
        }

        return Announcement::query()->visibleTo($user, Announcement::IN_APP)->orderByDesc('publish_at')->orderByDesc('id')->get();
    }

    public static function unreadCount(?User $user): int
    {
        if (! $user instanceof User || ! self::enabled()) {
            return 0;
        }

        return Announcement::query()
            ->visibleTo($user, Announcement::IN_APP)
            ->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $user->getKey())->whereNotNull('read_at'))
            ->count();
    }

    /**
     * IDs of the given announcements the user has not read yet.
     *
     * @param  Collection<int, Announcement>  $announcements
     * @return list<int>
     */
    public static function unreadIds(User $user, Collection $announcements): array
    {
        $read = AnnouncementRead::query()
            ->where('user_id', $user->getKey())
            ->whereIn('announcement_id', $announcements->modelKeys())
            ->whereNotNull('read_at')
            ->pluck('announcement_id')
            ->all();

        return array_values(array_diff($announcements->modelKeys(), $read));
    }

    /**
     * @param  list<int>  $ids
     */
    public static function markRead(User $user, array $ids): void
    {
        foreach ($ids as $id) {
            AnnouncementRead::query()->updateOrCreate(
                ['announcement_id' => $id, 'user_id' => $user->getKey()],
                ['read_at' => now()],
            );
        }
    }

    /**
     * The newest banner the user has not closed yet.
     */
    public static function banner(?User $user): ?Announcement
    {
        if (! $user instanceof User || ! self::enabled()) {
            return null;
        }

        return Announcement::query()
            ->visibleTo($user, Announcement::BANNER)
            ->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $user->getKey())->whereNotNull('dismissed_at'))
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Closing the banner: done for this user (it was seen, so it counts as read too).
     */
    public static function dismiss(User $user, Announcement $announcement): void
    {
        $read = AnnouncementRead::query()->firstOrNew(['announcement_id' => $announcement->getKey(), 'user_id' => $user->getKey()]);
        $read->read_at ??= now();
        $read->dismissed_at = now();
        $read->save();
    }
}
