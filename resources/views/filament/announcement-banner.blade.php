{{-- Announcement banner (channel banner), shown until closed (App\Announcements\Announcements::banner()). Same pattern as filament/oidc-nudge. --}}
{{-- Inline styles: the panel uses Filament's prebuilt CSS, which lacks most Tailwind utilities. --}}
@php($announcement = App\Announcements\Announcements::banner(auth()->user()))
@if ($announcement)
    <div
        data-announcement-banner="{{ $announcement->account_manager_uuid }}"
        role="region"
        aria-label="{{ $announcement->title() }}"
        style="margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 0.75rem; border: 1px solid {{ $announcement->isMaintenance() ? 'var(--warning-300)' : 'var(--primary-300)' }}; background: color-mix(in oklab, {{ $announcement->isMaintenance() ? 'var(--warning-500)' : 'var(--primary-500)' }} 10%, transparent);"
    >
        <x-filament::icon
            :icon="$announcement->isMaintenance() ? 'heroicon-o-wrench-screwdriver' : 'heroicon-o-megaphone'"
            style="width: 1.5rem; height: 1.5rem; color: {{ $announcement->isMaintenance() ? 'var(--warning-600)' : 'var(--primary-600)' }};"
        />
        <div style="flex: 1 1 16rem;">
            <p style="margin: 0; font-weight: 600;">{{ $announcement->title() }}</p>
            @if ($announcement->excerpt() !== '')
                <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--gray-500);">{{ $announcement->excerpt() }}</p>
            @endif
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
            @if ($announcement->link)
                <x-filament::button tag="a" :href="$announcement->link" target="_blank" rel="noopener" icon="heroicon-o-arrow-top-right-on-square">
                    {{ __('Learn more') }}
                </x-filament::button>
            @elseif ($announcement->hasChannel(App\Models\Announcement::IN_APP))
                <x-filament::button tag="a" :href="App\Filament\Pages\News::getUrl(panel: 'app')" icon="heroicon-o-bell">
                    {{ __('Read more') }}
                </x-filament::button>
            @endif
            <form method="POST" action="{{ route('announcements.dismiss', $announcement) }}" style="margin: 0;">
                @csrf
                <x-filament::button type="submit" color="gray">
                    {{ __('Close') }}
                </x-filament::button>
            </form>
        </div>
    </div>
@endif
