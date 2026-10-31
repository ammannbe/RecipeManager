<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Season') }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <script>
        (function () {
            var theme = localStorage.getItem('theme');

            if (theme === 'dark' || (! theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }());
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-0 h-112 w-160 rounded-full bg-[radial-gradient(circle,rgba(251,191,36,0.22),transparent_70%)] blur-3xl dark:bg-[radial-gradient(circle,rgba(251,191,36,0.1),transparent_70%)]"></div>
        <div class="absolute -top-44 right-0 h-112 w-xl rounded-full bg-[radial-gradient(circle,rgba(59,130,246,0.16),transparent_72%)] blur-3xl dark:bg-[radial-gradient(circle,rgba(59,130,246,0.12),transparent_72%)]"></div>
    </div>

    @include('partials.nav')

    @php($previousMonth = \App\Services\Season::previous($month))
    @php($nextMonth = \App\Services\Season::next($month))
    @php($buttonClass = 'inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-300 bg-white text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100')

    <main class="mx-auto grid w-full max-w-7xl gap-6 px-4 sm:px-6 [&>*]:min-w-0 py-8 pb-20 lg:gap-8 lg:py-10" x-data="{ yearTable: false }">
        <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="grid gap-2">
                <p class="text-[0.7rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Swiss seasonal calendar') }}</p>
                <h1 class="text-3xl font-black leading-tight tracking-[-0.04em] text-zinc-900 md:text-5xl dark:text-zinc-100">{{ __('In season in :month', ['month' => $months[$month]]) }}</h1>
            </div>

            <form method="GET" action="{{ route('recipes.season') }}" class="flex items-center gap-2">
                <a href="{{ route('recipes.season', ['month' => $previousMonth]) }}" class="{{ $buttonClass }}" aria-label="{{ __('Previous month') }}" title="{{ $months[$previousMonth] }}">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>

                <label for="month" class="sr-only">{{ __('Month') }}</label>
                <select id="month" name="month" onchange="this.form.requestSubmit()" class="h-9 rounded-xl border border-zinc-300 bg-white px-3 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    @foreach ($months as $value => $name)
                        <option value="{{ $value }}" @selected($value === $month)>{{ $name }}</option>
                    @endforeach
                </select>

                <a href="{{ route('recipes.season', ['month' => $nextMonth]) }}" class="{{ $buttonClass }}" aria-label="{{ __('Next month') }}" title="{{ $months[$nextMonth] }}">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                        <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </form>
        </header>

        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-4 [&>*]:min-w-0 shadow-[0_12px_32px_-24px_rgba(15,23,42,0.6)] md:p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-2">
                <h2 class="text-[0.7rem] font-bold uppercase tracking-widest text-zinc-600 dark:text-zinc-300">{{ __('Fresh from Swiss harvest') }}</h2>

                <div class="flex flex-wrap items-center gap-1.5" data-fresh-foods>
                    @forelse ($freshFoods as $food)
                        <span class="inline-flex items-center rounded-full border border-emerald-600 bg-emerald-50 px-3 py-1 text-xs font-semibold leading-none text-emerald-800 dark:border-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-400">{{ $food->name }}</span>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No foods with season data for this month.') }}</p>
                    @endforelse
                </div>
            </div>

            @if ($storageFoods->isNotEmpty())
                <div class="grid gap-2 border-t border-dashed border-zinc-300 pt-3 dark:border-zinc-700">
                    <h2 class="text-[0.7rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('From storage') }}</h2>

                    <div class="flex flex-wrap items-center gap-1.5" data-storage-foods>
                        @foreach ($storageFoods as $food)
                            <span class="inline-flex items-center rounded-full border border-zinc-300 bg-zinc-50 px-3 py-1 text-xs font-semibold leading-none text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400">{{ $food->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($yearFoods->isNotEmpty())
                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                        @click="yearTable = ! yearTable"
                        :aria-expanded="yearTable.toString()"
                        aria-controls="season-year-table"
                    >
                        <span x-text="yearTable ? '{{ __('Hide year overview') }}' : '{{ __('Show year overview') }}'">{{ __('Show year overview') }}</span>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4 transition" :class="{ 'rotate-180': yearTable }">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div id="season-year-table" class="mt-3 overflow-x-auto" x-show="yearTable" x-cloak>
                        <table class="w-full min-w-xl border-separate border-spacing-0.5 text-xs">
                            <thead>
                                <tr>
                                    {{-- w-1: the name column only takes what it needs, the months get the rest --}}
                                    <th class="sticky left-0 w-1 whitespace-nowrap bg-white pr-2 text-left font-semibold text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">{{ __('Food') }}</th>
                                    @foreach ($months as $value => $name)
                                        <th @class([
                                            'px-0.5 text-center font-semibold',
                                            'text-cyan-700 dark:text-cyan-400' => $value === $month,
                                            'text-zinc-500 dark:text-zinc-400' => $value !== $month,
                                        ]) title="{{ $name }}">{{ \App\Services\Season::shortMonthName($value) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($yearFoods as $food)
                                    <tr>
                                        <th class="sticky left-0 whitespace-nowrap bg-white pr-2 text-left font-medium text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">{{ $food->name }}</th>
                                        @foreach ($months as $value => $name)
                                            @php($fresh = $food->inSeason($value))
                                            @php($stored = ! $fresh && in_array($value, $food->storage_months ?? [], true))
                                            <td
                                                @class([
                                                    'h-5 rounded',
                                                    'bg-emerald-500 dark:bg-emerald-500/80' => $fresh,
                                                    'bg-emerald-200 dark:bg-emerald-500/35' => $stored,
                                                    'bg-zinc-100 dark:bg-zinc-800/60' => ! $fresh && ! $stored,
                                                    'ring-1 ring-cyan-500' => $value === $month,
                                                ])
                                                title="{{ $food->name }} · {{ $name }}{{ $fresh ? ': '.__('Fresh') : ($stored ? ': '.__('From storage') : '') }}"
                                            ></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-500 dark:bg-emerald-500/80"></span>{{ __('Fresh') }}</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-200 dark:bg-emerald-500/35"></span>{{ __('From storage') }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <header class="grid gap-2">
            <h2 class="text-2xl font-black leading-tight tracking-[-0.03em] text-zinc-900 md:text-3xl dark:text-zinc-100">{{ __('Seasonal recipes') }}</h2>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 cards:grid-cols-3 md:gap-5" data-season-recipes>
            @forelse ($recipes as $recipe)
                @include('recipes.partials.card', [
                    'recipe' => $recipe,
                    'seasonLabel' => trans_choice(':count ingredient in season|:count ingredients in season', $recipe->season_foods_count, ['count' => $recipe->season_foods_count]),
                ])
            @empty
                <p class="col-span-full rounded-2xl border border-zinc-200 bg-white p-4 text-center text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">{{ __('No recipes with seasonal ingredients found.') }}</p>
            @endforelse
        </section>
    </main>
</body>
</html>
