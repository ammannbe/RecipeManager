<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
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

    <main class="mx-auto grid w-full max-w-7xl gap-6 px-4 sm:px-6 [&>*]:min-w-0 py-8 pb-20 lg:gap-8 lg:py-10">
        @php($activeFilterCount = ($search !== '' ? 1 : 0) + ($quick ? 1 : 0) + ($seasonal ? 1 : 0) + ($complexity !== '' ? 1 : 0) + ($selectedCategory ? 1 : 0) + ($selectedCookbook ? 1 : 0) + count($selectedTags) + ($selectedSort !== 'created_at_desc' ? 1 : 0))

        <section
            class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-3 shadow-[0_12px_32px_-24px_rgba(15,23,42,0.6)] md:p-4 dark:border-zinc-800 dark:bg-zinc-900"
            x-data="{ open: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 text-left"
                @click="open = ! open"
                :aria-expanded="open.toString()"
                aria-controls="recipe-filters"
            >
                <span class="inline-flex items-center gap-2 text-[0.7rem] font-bold uppercase tracking-widest text-zinc-600 dark:text-zinc-300">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                        <path d="M3 5h18M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    {{ __('Filters') }}

                    @if ($activeFilterCount > 0)
                        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-cyan-50 px-1.5 py-0.5 text-[0.65rem] leading-none text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400">{{ $activeFilterCount }}</span>
                    @endif
                </span>

                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4 text-zinc-500 transition dark:text-zinc-400" :class="{ 'rotate-180': open }">
                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <form id="recipe-filters" method="GET" action="{{ route('recipes.index') }}" class="grid grid-cols-12 gap-3" data-auto-submit-filters x-show="open" @if ($activeFilterCount === 0) x-cloak @endif>
                <div class="col-span-12 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Quick') }}</p>

                        <label @class([
                            'inline-flex min-h-7 cursor-pointer select-none items-center rounded-full border px-3 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] leading-none transition',
                            'border-cyan-600 bg-cyan-50 text-cyan-700 shadow-sm dark:border-cyan-500 dark:bg-cyan-500/10 dark:text-cyan-400' => $quick,
                            'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100' => ! $quick,
                        ])>
                            <input type="checkbox" name="quick" value="1" class="sr-only" @checked($quick)>
                            {{ __('Max. 30 min') }}
                        </label>

                        <label @class([
                            'inline-flex min-h-7 cursor-pointer select-none items-center rounded-full border px-3 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] leading-none transition',
                            'border-cyan-600 bg-cyan-50 text-cyan-700 shadow-sm dark:border-cyan-500 dark:bg-cyan-500/10 dark:text-cyan-400' => $seasonal,
                            'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100' => ! $seasonal,
                        ])>
                            <input type="checkbox" name="seasonal" value="1" class="sr-only" @checked($seasonal)>
                            {{ __('Seasonal') }}
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-dashed border-zinc-300 pt-2 md:border-l md:border-t-0 md:pt-0 md:pl-3 dark:border-zinc-700">
                        <p class="text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Difficulty') }}</p>

                        @php($complexityOptions = ['' => __('All'), 'simple' => __('Simple'), 'normal' => __('Normal'), 'difficult' => __('Difficult')])
                        @foreach ($complexityOptions as $value => $label)
                            <label @class([
                                'inline-flex min-h-7 cursor-pointer select-none items-center rounded-full border px-3 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] leading-none transition',
                                'border-cyan-600 bg-cyan-50 text-cyan-700 shadow-sm dark:border-cyan-500 dark:bg-cyan-500/10 dark:text-cyan-400' => $complexity === $value,
                                'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100' => $complexity !== $value,
                            ])>
                                <input
                                    type="radio"
                                    name="complexity"
                                    value="{{ $value }}"
                                    class="sr-only"
                                    @checked($complexity === $value)
                                >
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-span-12 grid gap-1">
                    <label for="search" class="text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Search') }}</label>
                    <input
                        id="search"
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="{{ __('Search for recipes...') }}"
                        class="h-9 w-full rounded-xl border border-zinc-300 bg-white px-3 text-sm text-zinc-800 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                    >
                </div>

                <div class="col-span-12 grid gap-1 md:col-span-3">
                    <label for="category" class="text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Category') }}</label>
                    <select id="category" name="category" class="h-9 w-full rounded-xl border border-zinc-300 bg-white px-3 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-12 grid gap-1 md:col-span-3">
                    <label for="cookbook" class="text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Cookbook') }}</label>
                    <select id="cookbook" name="cookbook" class="h-9 w-full rounded-xl border border-zinc-300 bg-white px-3 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="">{{ __('All cookbooks') }}</option>
                        @foreach ($cookbooks as $cookbook)
                            <option value="{{ $cookbook->id }}" @selected($selectedCookbook === $cookbook->id)>{{ $cookbook->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-12 grid gap-1 md:col-span-3">
                    <label for="sort" class="text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="h-9 w-full rounded-xl border border-zinc-300 bg-white px-3 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="created_at_desc" @selected($selectedSort === 'created_at_desc')>{{ __('Newest first') }}</option>
                        <option value="created_at_asc" @selected($selectedSort === 'created_at_asc')>{{ __('Oldest first') }}</option>
                        <option value="name_asc" @selected($selectedSort === 'name_asc')>{{ __('Name A-Z') }}</option>
                        <option value="name_desc" @selected($selectedSort === 'name_desc')>{{ __('Name Z-A') }}</option>
                        <option value="complexity_asc" @selected($selectedSort === 'complexity_asc')>{{ __('Difficulty low-high') }}</option>
                        <option value="complexity_desc" @selected($selectedSort === 'complexity_desc')>{{ __('Difficulty high-low') }}</option>
                    </select>
                </div>

                @if ($tags->isNotEmpty())
                    <div class="col-span-12 grid gap-1">
                        <p class="text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Tags') }}</p>

                        <div class="flex flex-wrap items-center gap-2">
                            @foreach ($tags as $tag)
                                @php($isSelected = in_array($tag->id, $selectedTags, true))

                                <label @class([
                                    'inline-flex min-h-7 cursor-pointer select-none items-center rounded-full border px-3 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] leading-none transition',
                                    'border-cyan-600 bg-cyan-50 text-cyan-700 shadow-sm dark:border-cyan-500 dark:bg-cyan-500/10 dark:text-cyan-400' => $isSelected,
                                    'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100' => ! $isSelected,
                                ])>
                                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="sr-only" @checked($isSelected)>
                                    {{ $tag->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="col-span-12 flex items-center gap-2 pt-1">
                    <a href="{{ route('recipes.index') }}" class="inline-flex h-8 items-center rounded-xl border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">{{ __('Reset') }}</a>
                </div>
            </form>
        </section>

        <header class="grid gap-2">
            <h1 class="text-3xl font-black leading-tight tracking-[-0.04em] text-zinc-900 md:text-5xl dark:text-zinc-100">{{ __('Recipes') }}</h1>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 cards:grid-cols-3 md:gap-5">
            @forelse ($recipes as $recipe)
                @include('recipes.partials.card', ['recipe' => $recipe])
            @empty
                <p class="col-span-full rounded-2xl border border-zinc-200 bg-white p-4 text-center text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">{{ __('No recipes found.') }}</p>
            @endforelse
        </section>

        @if ($recipes->hasPages())
            <nav class="grid grid-cols-1 items-center gap-3 pt-1 sm:grid-cols-[auto_1fr_auto]" aria-label="{{ __('Pagination') }}">
                <a
                    href="{{ $recipes->onFirstPage() ? '#' : $recipes->previousPageUrl() }}"
                    @class([
                        'inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-bold transition sm:justify-self-start',
                        'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! $recipes->onFirstPage(),
                        'pointer-events-none border-zinc-200 bg-zinc-100 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600' => $recipes->onFirstPage(),
                    ])
                    style="min-width: 2.5rem; height: 2.5rem; padding-inline: 0.75rem;"
                >
                    {{ __('Previous') }}
                </a>

                <div class="flex flex-wrap items-center justify-center gap-1.5 sm:justify-self-center">
                    @foreach ($paginationPages as $page)
                        @if (is_null($page))
                            <span class="px-1 text-sm font-bold text-zinc-500 dark:text-zinc-400">…</span>
                        @else
                            <a
                                href="{{ $recipes->url($page) }}"
                                @class([
                                    'inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-bold transition',
                                    'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => $page !== $currentPage,
                                    'border-cyan-600 bg-cyan-50 text-cyan-700 dark:border-cyan-500 dark:bg-cyan-500/10 dark:text-cyan-400' => $page === $currentPage,
                                ])
                                style="min-width: 2.5rem; height: 2.5rem; padding-inline: 0.75rem;"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                </div>

                <a
                    href="{{ $recipes->hasMorePages() ? $recipes->nextPageUrl() : '#' }}"
                    @class([
                        'inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-bold transition sm:justify-self-end',
                        'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => $recipes->hasMorePages(),
                        'pointer-events-none border-zinc-200 bg-zinc-100 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600' => ! $recipes->hasMorePages(),
                    ])
                    style="min-width: 2.5rem; height: 2.5rem; padding-inline: 0.75rem;"
                >
                    {{ __('Next') }}
                </a>
            </nav>
        @endif
    </main>

    <script>
        (function () {
            const form = document.querySelector('[data-auto-submit-filters]');

            if (! form) {
                return;
            }

            let searchTimer;

            const submitForm = () => form.requestSubmit();

            form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach((field) => {
                field.addEventListener('change', submitForm);
            });

            const searchInput = form.querySelector('input[name="search"]');

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(submitForm, 800);
                });
            }
        }());
    </script>
</body>
</html>
