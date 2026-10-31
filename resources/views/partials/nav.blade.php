@php
    $navLinks = [
        ['label' => __('Recipes'), 'url' => route('recipes.index'), 'active' => request()->routeIs('recipes.index', 'recipes.show'), 'current' => request()->routeIs('recipes.index')],
        ['label' => __('Season calendar'), 'url' => route('recipes.season'), 'active' => request()->routeIs('recipes.season'), 'current' => request()->routeIs('recipes.season')],
    ];
    $accountLink = auth()->check() ? ['label' => __('Backend'), 'url' => url('/admin')] : ['label' => __('Login'), 'url' => url('/admin/login')];
@endphp
@php($iconButtonClass = 'inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-300 bg-white text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100')

{{-- Not blurred itself: backdrop-filter would make the fixed backdrop relative to the header --}}
<header class="sticky top-0 z-30" x-data="siteNav" @keydown.escape.window="close()" @keydown="trap($event)" data-site-nav>
    <div
        class="fixed inset-0 bg-zinc-950/40 backdrop-blur-[2px] md:hidden"
        x-show="open"
        x-transition.opacity
        x-cloak
        @click="close()"
        aria-hidden="true"
    ></div>

    <div class="relative border-b border-zinc-200/80 bg-white/90 backdrop-blur dark:border-zinc-800/80 dark:bg-zinc-950/90">
        <div class="mx-auto flex h-14 w-full max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 md:h-16">
            <a href="{{ route('recipes.index') }}" class="inline-flex min-w-0 items-center gap-3 rounded-xl font-semibold tracking-tight text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:text-zinc-100">
                <img src="{{ asset('favicon.ico') }}" alt="" class="h-8 w-8 shrink-0 rounded-lg">
                <span class="truncate">{{ config('app.name') }}</span>
            </a>

            <div class="hidden items-center gap-6 md:flex">
                <nav aria-label="{{ __('Main navigation') }}" class="flex items-center gap-1">
                    @foreach ($navLinks as $link)
                        <a
                            href="{{ $link['url'] }}"
                            @if ($link['current']) aria-current="page" @endif
                            @class([
                                'inline-flex h-9 items-center rounded-xl px-3 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600',
                                'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400' => $link['active'],
                                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! $link['active'],
                            ])
                        >{{ $link['label'] }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-2 border-l border-zinc-200 pl-6 dark:border-zinc-800">
                    @include('partials.locale-switcher')

                    <button
                        type="button"
                        class="{{ $iconButtonClass }}"
                        @click="toggle()"
                        :aria-label="dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
                        aria-label="{{ __('Switch to dark mode') }}"
                    >
                        <svg x-show="! dark" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4.5 w-4.5">
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2" />
                            <path d="M12 2v2M12 20v2M4 12H2M22 12h-2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41M19.07 19.07l-1.41-1.41M6.34 6.34 4.93 4.93" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <svg x-show="dark" x-cloak viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4.5 w-4.5">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <a href="{{ $accountLink['url'] }}" class="inline-flex h-9 items-center rounded-xl bg-cyan-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:bg-cyan-500 dark:text-zinc-950 dark:hover:bg-cyan-400">{{ $accountLink['label'] }}</a>
                </div>
            </div>

            <button
                type="button"
                class="-mr-1.5 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 md:hidden dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                x-ref="toggle"
                @click="open ? close() : show()"
                aria-controls="mobile-navigation"
                aria-expanded="false"
                :aria-expanded="open.toString()"
                aria-label="{{ __('Menu') }}"
                data-nav-toggle
            >
                <svg x-show="! open" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-6 w-6">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                <svg x-show="open" x-cloak viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-6 w-6">
                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </div>

    <div
        id="mobile-navigation"
        class="absolute inset-x-0 top-full max-h-[calc(100dvh-3.5rem)] overflow-y-auto border-b border-zinc-200 bg-white shadow-xl md:hidden dark:border-zinc-800 dark:bg-zinc-950"
        x-ref="panel"
        x-show="open"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="-translate-y-2 opacity-0"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-end="-translate-y-2 opacity-0"
        x-cloak
    >
        <div class="grid gap-4 px-4 pt-3 pb-[calc(1.25rem+env(safe-area-inset-bottom))] sm:px-6">
            <nav aria-label="{{ __('Main navigation') }}" class="grid gap-1">
                @foreach ($navLinks as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @click="close(false)"
                        @if ($link['current']) aria-current="page" @endif
                        @class([
                            'flex h-12 items-center rounded-xl px-3 text-base font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600',
                            'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400' => $link['active'],
                            'text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900' => ! $link['active'],
                        ])
                    >{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="grid gap-1 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                @if (count(config('app.locales', [])) > 1)
                    <div class="flex h-12 items-center justify-between gap-3 px-3">
                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Language') }}</span>
                        @include('partials.locale-switcher', ['large' => true])
                    </div>
                @endif

                <button
                    type="button"
                    role="switch"
                    class="flex h-12 w-full items-center justify-between gap-3 rounded-xl px-3 text-left text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:text-zinc-300 dark:hover:bg-zinc-900"
                    @click="toggle()"
                    aria-checked="false"
                    :aria-checked="dark.toString()"
                >
                    <span>{{ __('Dark mode') }}</span>
                    <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-zinc-300 transition dark:bg-cyan-500" aria-hidden="true">
                        <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition dark:translate-x-5"></span>
                    </span>
                </button>
            </div>

            <a href="{{ $accountLink['url'] }}" @click="close(false)" class="flex h-12 items-center justify-center rounded-xl bg-cyan-700 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:bg-cyan-500 dark:text-zinc-950 dark:hover:bg-cyan-400">{{ $accountLink['label'] }}</a>
        </div>
    </div>
</header>
