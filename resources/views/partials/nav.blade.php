<nav class="sticky top-0 z-20 border-b border-zinc-200/80 bg-white/90 backdrop-blur dark:border-zinc-800/80 dark:bg-zinc-950/90">
    <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-2 px-4 py-3 sm:gap-4 sm:px-6 sm:py-4">
        <a href="{{ route('recipes.index') }}" class="inline-flex min-w-0 items-center gap-3 font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
            <img src="{{ asset('favicon.ico') }}" alt="{{ config('app.name') }}" class="h-8 w-8 shrink-0 rounded-lg">
            {{-- Phones: only the icon, so the buttons fit --}}
            <span class="hidden truncate sm:inline">{{ config('app.name') }}</span>
        </a>

        <div class="flex shrink-0 items-center gap-1.5 sm:gap-2" x-data="themeToggle">
            <a href="{{ route('recipes.season') }}" aria-label="{{ __('Season calendar') }}" @class([
                'inline-flex h-9 items-center gap-1.5 rounded-xl border px-2.5 text-sm font-semibold shadow-sm transition sm:px-4',
                'border-cyan-600 bg-cyan-50 text-cyan-700 dark:border-cyan-500 dark:bg-cyan-500/10 dark:text-cyan-400' => request()->routeIs('recipes.season'),
                'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('recipes.season'),
            ])>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4 shrink-0">
                    <path d="M11 20A7 7 0 0 1 4 13c0-5 4-9 16-9 0 12-4 16-9 16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M4 20c4-4 7-7 11-10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                <span class="hidden sm:inline">{{ __('Season calendar') }}</span>
            </a>

            @include('partials.locale-switcher')

            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-300 bg-white text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                @click="toggle()"
                :aria-label="dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
            >
                <svg x-show="! dark" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4.5 w-4.5">
                    <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2" />
                    <path d="M12 2v2M12 20v2M4 12H2M22 12h-2M19.07 4.93l-1.41 1.41M6.34 17.66l-1.41 1.41M19.07 19.07l-1.41-1.41M6.34 6.34 4.93 4.93" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                <svg x-show="dark" x-cloak viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4.5 w-4.5">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            @guest
                <a href="{{ url('/admin/login') }}" class="inline-flex h-9 items-center rounded-xl border border-zinc-300 bg-white px-3 text-sm font-semibold sm:px-4 text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">{{ __('Login') }}</a>
            @endguest

            @auth
                <a href="{{ url('/admin') }}" class="inline-flex h-9 items-center rounded-xl border border-zinc-300 bg-white px-3 text-sm font-semibold sm:px-4 text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">{{ __('Backend') }}</a>
            @endauth
        </div>
    </div>
</nav>
