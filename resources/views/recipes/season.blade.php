<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Season calendar') }} · {{ config('app.name') }}</title>
    @include('partials.favicon')
    @include('partials.meta', ['title' => __('Season calendar').' · '.config('app.name')])
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

    {{-- Season calendar: food × month, the chosen month (default: now) highlighted, its foods first --}}
    <main class="mx-auto grid w-full max-w-7xl gap-6 px-4 sm:px-6 [&>*]:min-w-0 py-8 pb-20 lg:gap-8 lg:py-10">
        <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="grid gap-2">
                <p class="text-[0.7rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Swiss seasonal calendar') }}</p>
                <h1 class="text-3xl font-black leading-tight tracking-[-0.04em] text-zinc-900 md:text-5xl dark:text-zinc-100">{{ __('Season calendar') }}</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ trans_choice(':count food is in season in :month.|:count foods are in season in :month.', $freshCount, ['count' => $freshCount, 'month' => $months[$month]]) }}
                    <a href="{{ route('recipes.index', ['seasonal' => 1]) }}" class="font-semibold text-violet-700 hover:underline dark:text-violet-400">{{ __('Show seasonal recipes') }}</a>
                </p>
            </div>

            <form method="GET" action="{{ route('recipes.season') }}" class="flex items-center gap-2">
                <a href="{{ route('recipes.season', ['month' => $previousMonth]) }}" class="{{ $buttonClass }}" aria-label="{{ __('Previous month') }}" title="{{ $months[$previousMonth] }}">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>

                <label for="month" class="sr-only">{{ __('Month') }}</label>
                <select id="month" name="month" onchange="this.form.requestSubmit()" class="h-9 rounded-xl border border-zinc-300 bg-white px-3 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-violet-600 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
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

        <section class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-3 shadow-[0_12px_32px_-24px_rgba(15,23,42,0.6)] md:p-5 dark:border-zinc-800 dark:bg-zinc-900 [&>*]:min-w-0">
            @if ($foods->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No foods with season data yet.') }}</p>
            @else
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-zinc-600 dark:text-zinc-400">
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-700 dark:bg-emerald-400"></span>{{ __('Fresh in :month', ['month' => $months[$month]]) }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-400 dark:bg-emerald-600"></span>{{ __('Fresh') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-100 dark:bg-emerald-900"></span>{{ __('From storage') }}</span>
                </div>

                {{-- Narrow screens: start scrolled to the chosen month --}}
                <div class="overflow-x-auto" data-season-calendar
                    x-data
                    x-init="$nextTick(() => { const current = $el.querySelector('[data-current-month]'); if (current && $el.scrollWidth > $el.clientWidth) $el.scrollLeft = current.offsetLeft - $el.clientWidth / 2; })">
                    <table class="w-full border-separate border-spacing-0.5 text-xs">
                        <thead>
                            <tr>
                                {{-- Names wrap after ~9rem ("Schwarze Johannisbeeren"), the months get the rest --}}
                                <th class="sticky left-0 z-10 w-36 min-w-24 bg-white pr-2 text-left font-semibold text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">{{ __('Food') }}</th>
                                @foreach ($months as $value => $name)
                                    <th @class([
                                        'min-w-7 px-0.5 pb-1 text-center font-semibold',
                                        'text-emerald-800 underline decoration-2 underline-offset-4 dark:text-emerald-300' => $value === $month,
                                        'text-zinc-500 dark:text-zinc-400' => $value !== $month,
                                    ]) title="{{ $name }}" @if ($value === $month) data-current-month @endif>{{ \App\Services\Season::shortMonthName($value) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($foods as $food)
                                @php($freshNow = $food->inSeason($month))
                                <tr data-food="{{ $food->name }}">
                                    <th @class([
                                        'sticky left-0 z-10 w-36 max-w-36 bg-white py-0.5 pr-2 text-left leading-tight break-words dark:bg-zinc-900',
                                        'font-semibold text-zinc-900 dark:text-zinc-100' => $freshNow,
                                        'font-medium text-zinc-600 dark:text-zinc-400' => ! $freshNow,
                                    ])>{{ $food->name }}</th>
                                    @foreach ($months as $value => $name)
                                        @php($fresh = $food->inSeason($value))
                                        @php($stored = ! $fresh && in_array($value, $food->storage_months ?? [], true))
                                        <td
                                            @class([
                                                'h-6 rounded',
                                                'bg-emerald-700 dark:bg-emerald-400' => $fresh && $value === $month,
                                                'bg-emerald-400 dark:bg-emerald-600' => $fresh && $value !== $month,
                                                'bg-emerald-100 dark:bg-emerald-900' => $stored,
                                                'bg-zinc-100 dark:bg-zinc-800/70' => ! $fresh && ! $stored,
                                            ])
                                            title="{{ $food->name }} · {{ $name }}{{ $fresh ? ': '.__('Fresh') : ($stored ? ': '.__('From storage') : '') }}"
                                        ></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</body>
</html>
