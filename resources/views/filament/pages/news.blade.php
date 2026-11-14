{{-- Announcements with the channel in_app (App\Filament\Pages\News). Inline styles: the panel uses Filament's prebuilt CSS. --}}
<x-filament-panels::page>
    @forelse ($this->getAnnouncements() as $announcement)
        <x-filament::section data-announcement="{{ $announcement->account_manager_uuid }}">
            <x-slot name="heading">
                {{ $announcement->title() }}
                @if (in_array($announcement->getKey(), $unread, true))
                    <x-filament::badge style="display: inline-flex; margin-inline-start: 0.5rem; vertical-align: middle;" data-unread>{{ __('New') }}</x-filament::badge>
                @endif
            </x-slot>
            <x-slot name="description">
                <time datetime="{{ $announcement->publish_at->toIso8601String() }}">{{ $announcement->publish_at->timezone('Europe/Zurich')->isoFormat('LL') }}</time>
                @if ($announcement->isMaintenance())
                    · {{ __('Maintenance') }}
                @endif
            </x-slot>

            @if ($announcement->image_url)
                <img src="{{ $announcement->image_url }}" alt="" loading="lazy" style="display: block; max-width: 100%; max-height: 20rem; margin-bottom: 1rem; border-radius: 0.5rem;">
            @endif

            {{-- Sanitized by the AccountManager (CommonMark, HTML escaped, unsafe links removed). --}}
            <div class="fi-prose">{!! $announcement->bodyHtml() !!}</div>

            @if ($announcement->link)
                <div style="margin-top: 1rem;">
                    <x-filament::link :href="$announcement->link" target="_blank" rel="noopener" icon="heroicon-m-arrow-top-right-on-square" icon-position="after">
                        {{ __('Learn more') }}
                    </x-filament::link>
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p style="margin: 0; color: var(--gray-500);">{{ __('No news at the moment.') }}</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
