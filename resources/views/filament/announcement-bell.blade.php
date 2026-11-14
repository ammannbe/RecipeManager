{{-- Bell in the top bar with the number of unread announcements, opens "News" (ANNOUNCEMENTS_DRIVER=account-manager). --}}
@if (App\Announcements\Announcements::enabled() && auth()->check())
    @php($unread = App\Announcements\Announcements::unreadCount(auth()->user()))
    <x-filament::icon-button
        data-announcement-bell
        :href="App\Filament\Pages\News::getUrl(panel: 'app')"
        tag="a"
        :icon="$unread > 0 ? 'heroicon-o-bell-alert' : 'heroicon-o-bell'"
        :label="__('News')"
        :tooltip="__('News')"
        :badge="$unread > 0 ? $unread : null"
        color="gray"
    />
@endif
