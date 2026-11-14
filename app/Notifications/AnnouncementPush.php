<?php

namespace App\Notifications;

use App\Filament\Pages\News;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Web Push of an announcement (channel push): title and excerpt in the user's locale, opens "News". Sent by
 * AnnouncementSender through the WebPushChannel directly (it needs the delivery reports), not queued itself.
 */
class AnnouncementPush extends Notification
{
    public function __construct(public Announcement $announcement) {}

    /**
     * @return list<class-string>
     */
    public function via(User $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(User $notifiable, Notification $notification): WebPushMessage
    {
        $locale = $notifiable->locale ?: config('app.locale');

        return (new WebPushMessage)
            ->title($this->announcement->title($locale))
            ->body($this->announcement->excerpt($locale))
            ->icon('/web-app-manifest-192x192.png')
            ->badge('/favicon-96x96.png')
            ->tag('announcement-'.$this->announcement->account_manager_uuid)
            ->data(['url' => News::getUrl(panel: 'app')]);
    }
}
