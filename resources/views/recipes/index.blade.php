<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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

    <main class="mx-auto grid w-full max-w-7xl gap-6 px-4 sm:px-6 [&>*]:min-w-0 py-8 pb-32 md:pb-20 lg:gap-8 lg:py-10">
        @php($panelFilterCount = collect($activeFilters)->where('key', '!=', 'search')->count())
        @php($labelClass = 'text-[0.6rem] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400')
        @php($fieldClass = 'h-11 w-full rounded-xl border border-zinc-300 bg-white px-3 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20 md:h-9 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100')
        {{-- Checked state via CSS: in the mobile sheet changes are only applied on submit --}}
        @php($optionClass = 'inline-flex min-h-9 cursor-pointer select-none items-center rounded-full border border-zinc-300 bg-white px-3 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] leading-none text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-900 has-checked:border-cyan-600 has-checked:bg-cyan-50 has-checked:text-cyan-700 has-checked:shadow-sm has-focus-visible:ring-2 has-focus-visible:ring-cyan-500/40 md:min-h-7 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100 dark:has-checked:border-cyan-500 dark:has-checked:bg-cyan-500/10 dark:has-checked:text-cyan-400')

        {{-- Filters: inline with instant submit on desktop, a bottom sheet applied with "Show recipes" on phones --}}
        <section
            class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-3 shadow-[0_12px_32px_-24px_rgba(15,23,42,0.6)] md:p-4 dark:border-zinc-800 dark:bg-zinc-900"
            x-data="recipeFilters({ expanded: {{ $panelFilterCount > 0 ? 'true' : 'false' }} })"
            @keydown.escape.window="closeSheet()"
            @keydown="trap($event)"
        >
            <form id="recipe-filters" x-ref="form" method="GET" action="{{ route('recipes.index') }}" class="grid gap-3" role="search" data-auto-submit-filters>
                <div class="flex items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <label for="search" class="sr-only">{{ __('Search') }}</label>
                        <input
                            id="search"
                            x-ref="search"
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            enterkeyhint="search"
                            placeholder="{{ __('Search for recipes...') }}"
                            class="{{ $fieldClass }} pr-11 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 [&::-webkit-search-cancel-button]:hidden"
                        >
                        <button type="submit" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-xl text-zinc-500 transition hover:text-cyan-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400" aria-label="{{ __('Search') }}">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4.5 w-4.5">
                                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                                <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="hidden h-9 shrink-0 items-center gap-2 rounded-xl border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 md:inline-flex dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        @click="expanded = ! expanded"
                        aria-expanded="{{ $panelFilterCount > 0 ? 'true' : 'false' }}"
                        :aria-expanded="expanded.toString()"
                        aria-controls="recipe-filter-panel"
                    >
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                            <path d="M3 5h18M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        {{ __('Filters') }}

                        @if ($panelFilterCount > 0)
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-cyan-50 px-1.5 py-0.5 text-[0.65rem] leading-none font-bold text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400">{{ $panelFilterCount }}</span>
                        @endif

                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4 text-zinc-500 transition dark:text-zinc-400" :class="{ 'rotate-180': expanded }">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                @if ($activeFilters !== [])
                    <div class="flex flex-wrap items-center gap-2" data-active-filters>
                        @foreach ($activeFilters as $filter)
                            <a
                                href="{{ $filter['url'] }}"
                                class="inline-flex h-8 max-w-full items-center gap-1 rounded-full border border-cyan-600/30 bg-cyan-50 pr-1.5 pl-3 text-xs font-semibold text-cyan-800 transition hover:border-cyan-600 hover:bg-cyan-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-300 dark:hover:border-cyan-500 dark:hover:bg-cyan-500/20"
                                aria-label="{{ __('Remove filter :name', ['name' => $filter['label']]) }}"
                                data-filter-chip="{{ $filter['key'] }}"
                            >
                                <span class="truncate">{{ $filter['label'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4 shrink-0 opacity-70">
                                    <path d="M7 7l10 10M17 7 7 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </a>
                        @endforeach

                        <a href="{{ route('recipes.index') }}" class="inline-flex h-8 items-center rounded-full px-2 text-xs font-semibold text-zinc-600 underline decoration-zinc-300 underline-offset-4 transition hover:text-zinc-900 hover:decoration-zinc-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100">{{ __('Reset all') }}</a>
                    </div>
                @endif

                <div
                    class="fixed inset-0 z-40 bg-zinc-950/50 backdrop-blur-[2px] md:hidden"
                    x-show="sheetOpen"
                    x-transition.opacity
                    x-cloak
                    @click="closeSheet()"
                    aria-hidden="true"
                ></div>

                <div
                    id="recipe-filter-panel"
                    x-ref="panel"
                    @class([
                        'max-md:hidden max-md:fixed max-md:inset-x-0 max-md:bottom-0 max-md:z-50 max-md:flex max-md:max-h-[90dvh] max-md:flex-col max-md:rounded-t-2xl max-md:bg-white max-md:shadow-2xl max-md:dark:bg-zinc-900',
                        'md:border-t md:border-zinc-200 md:pt-4 md:dark:border-zinc-800',
                        'md:hidden' => $panelFilterCount === 0,
                    ])
                    :class="{ 'max-md:hidden': ! sheetOpen, 'md:hidden': ! expanded }"
                    :role="sheetOpen ? 'dialog' : null"
                    :aria-modal="sheetOpen ? 'true' : null"
                    :aria-labelledby="sheetOpen ? 'recipe-filter-title' : null"
                    data-filter-panel
                >
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 py-2 pr-2 pl-4 md:hidden dark:border-zinc-800">
                        <h2 id="recipe-filter-title" class="text-base font-bold text-zinc-900 dark:text-zinc-100">{{ __('Filters') }}</h2>
                        <button
                            type="button"
                            x-ref="close"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            @click="closeSheet()"
                            aria-label="{{ __('Close') }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-5 w-5">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-12 gap-5 max-md:min-h-0 max-md:flex-1 max-md:overflow-y-auto max-md:overscroll-contain max-md:p-4 md:gap-3">
                        <div class="col-span-12 grid gap-5 md:flex md:items-center md:gap-3">
                            <div class="grid gap-2 md:flex md:flex-wrap md:items-center" role="group" aria-labelledby="filter-quick-label">
                                <p id="filter-quick-label" class="{{ $labelClass }}">{{ __('Quick') }}</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="{{ $optionClass }}">
                                        <input type="checkbox" name="quick" value="1" class="sr-only" @checked($quick)>
                                        {{ __('Max. 30 min') }}
                                    </label>

                                    <label class="{{ $optionClass }}">
                                        <input type="checkbox" name="seasonal" value="1" class="sr-only" @checked($seasonal)>
                                        {{ __('Seasonal') }}
                                    </label>
                                </div>
                            </div>

                            <div class="grid gap-2 md:flex md:flex-wrap md:items-center md:border-l md:border-dashed md:border-zinc-300 md:pl-3 dark:md:border-zinc-700" role="radiogroup" aria-labelledby="filter-difficulty-label">
                                <p id="filter-difficulty-label" class="{{ $labelClass }}">{{ __('Difficulty') }}</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    @php($complexityOptions = ['' => __('All'), 'simple' => __('Simple'), 'normal' => __('Normal'), 'difficult' => __('Difficult')])
                                    @foreach ($complexityOptions as $value => $label)
                                        <label class="{{ $optionClass }}">
                                            <input type="radio" name="complexity" value="{{ $value }}" class="sr-only" @checked($complexity === $value)>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-span-12 grid gap-1 md:col-span-4">
                            <label for="category" class="{{ $labelClass }}">{{ __('Category') }}</label>
                            <select id="category" name="category" class="{{ $fieldClass }}">
                                <option value="">{{ __('All categories') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-12 grid gap-1 md:col-span-4">
                            <label for="cookbook" class="{{ $labelClass }}">{{ __('Cookbook') }}</label>
                            <select id="cookbook" name="cookbook" class="{{ $fieldClass }}">
                                <option value="">{{ __('All cookbooks') }}</option>
                                @foreach ($cookbooks as $cookbook)
                                    <option value="{{ $cookbook->id }}" @selected($selectedCookbook === $cookbook->id)>{{ $cookbook->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-12 grid gap-1 md:col-span-4">
                            <label for="sort" class="{{ $labelClass }}">{{ __('Sort') }}</label>
                            <select id="sort" name="sort" class="{{ $fieldClass }}">
                                @foreach ($sortLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedSort === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($tags->isNotEmpty())
                            <div class="col-span-12 grid gap-2" role="group" aria-labelledby="filter-tags-label">
                                <p id="filter-tags-label" class="{{ $labelClass }}">{{ __('Tags') }}</p>

                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach ($tags as $tag)
                                        <label class="{{ $optionClass }}">
                                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="sr-only" @checked(in_array($tag->id, $selectedTags, true))>
                                            {{ $tag->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-[auto_1fr] gap-3 border-t border-zinc-200 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] md:hidden dark:border-zinc-800">
                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100" @click="clearFields()">{{ __('Reset') }}</button>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-cyan-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:bg-cyan-500 dark:text-zinc-950 dark:hover:bg-cyan-400">{{ __('Show recipes') }}</button>
                    </div>
                </div>
            </form>

            <div class="pointer-events-none fixed inset-x-0 bottom-0 z-20 flex justify-center px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] md:hidden" x-show="! sheetOpen">
                <button
                    type="button"
                    x-ref="opener"
                    class="pointer-events-auto inline-flex h-12 items-center gap-2 rounded-full bg-zinc-900 px-5 text-sm font-semibold text-white shadow-[0_10px_30px_-8px_rgba(15,23,42,0.6)] ring-1 ring-white/10 transition hover:bg-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 dark:bg-zinc-100 dark:text-zinc-900 dark:ring-zinc-950/10 dark:hover:bg-white"
                    @click="openSheet()"
                    aria-haspopup="dialog"
                    aria-controls="recipe-filter-panel"
                    aria-expanded="false"
                    :aria-expanded="sheetOpen.toString()"
                    data-filter-opener
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                        <path d="M3 5h18M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    {{ __('Filters') }}

                    @if ($panelFilterCount > 0)
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-cyan-500 px-1.5 text-[0.7rem] leading-none font-bold text-zinc-950">{{ $panelFilterCount }}</span>
                    @endif
                </button>
            </div>
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
</body>
</html>
