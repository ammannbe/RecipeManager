<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900">
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-0 h-112 w-160 rounded-full bg-[radial-gradient(circle,rgba(251,191,36,0.22),transparent_70%)] blur-3xl"></div>
        <div class="absolute -top-44 right-0 h-112 w-xl rounded-full bg-[radial-gradient(circle,rgba(59,130,246,0.16),transparent_72%)] blur-3xl"></div>
    </div>

    <nav class="sticky top-0 z-20 border-b border-zinc-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-4">
            <a href="{{ route('recipes.index') }}" class="inline-flex items-center gap-3 font-semibold tracking-tight text-zinc-900">
                <img src="{{ asset('favicon.ico') }}" alt="{{ config('app.name') }}" class="h-8 w-8 rounded-lg">
                <span>{{ config('app.name') }}</span>
            </a>

            @guest
                <a href="{{ url('/admin/login') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900">{{ __('Login') }}</a>
            @endguest

            @auth
                <a href="{{ url('/admin') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900">{{ __('Backend') }}</a>
            @endauth
        </div>
    </nav>

    <main class="mx-auto grid w-full max-w-5xl gap-6 px-6 py-8 pb-20 lg:gap-8 lg:py-10">
        <header class="grid gap-2">
            <h1 class="text-3xl font-black leading-tight tracking-[-0.04em] text-zinc-900 md:text-5xl">{{ __('Recipes') }}</h1>
        </header>

        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-[0_12px_32px_-24px_rgba(15,23,42,0.6)] md:p-6">
            <form method="GET" action="{{ route('recipes.index') }}" class="grid grid-cols-12 gap-5" data-auto-submit-filters>
                <div class="col-span-12 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="flex flex-wrap items-center gap-4">
                        <p class="text-[0.66rem] font-bold uppercase tracking-widest text-zinc-500">{{ __('Quick') }}</p>

                        <label @class([
                            'inline-flex min-h-10 cursor-pointer select-none items-center rounded-full border px-5 py-2 text-[0.7rem] font-bold uppercase tracking-[0.1em] leading-none transition',
                            'border-cyan-600 bg-cyan-50 text-cyan-700 shadow-sm' => $quick,
                            'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:text-zinc-900' => ! $quick,
                        ])>
                            <input type="checkbox" name="quick" value="1" class="sr-only" @checked($quick)>
                            {{ __('Max. 30 min') }}
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-zinc-300 pt-3 md:border-l md:border-t-0 md:pt-0 md:pl-4">
                        <p class="text-[0.66rem] font-bold uppercase tracking-widest text-zinc-500">{{ __('Difficulty') }}</p>

                        @php($complexityOptions = ['' => __('All'), 'simple' => __('Simple'), 'normal' => __('Normal'), 'difficult' => __('Difficult')])
                        @foreach ($complexityOptions as $value => $label)
                            <label @class([
                                'inline-flex min-h-10 cursor-pointer select-none items-center rounded-full border px-5 py-2 text-[0.7rem] font-bold uppercase tracking-[0.1em] leading-none transition',
                                'border-cyan-600 bg-cyan-50 text-cyan-700 shadow-sm' => $complexity === $value,
                                'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:text-zinc-900' => $complexity !== $value,
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

                <div class="col-span-12 grid gap-1 md:col-span-6">
                    <label for="search" class="text-[0.66rem] font-bold uppercase tracking-widest text-zinc-500">{{ __('Search') }}</label>
                    <input
                        id="search"
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="{{ __('Search for recipes...') }}"
                        class="h-12 w-full rounded-2xl border border-zinc-300 bg-white px-4 text-sm text-zinc-800 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20"
                    >
                </div>

                <div class="col-span-12 grid gap-1 md:col-span-3">
                    <label for="category" class="text-[0.66rem] font-bold uppercase tracking-widest text-zinc-500">{{ __('Category') }}</label>
                    <select id="category" name="category" class="h-12 w-full rounded-2xl border border-zinc-300 bg-white px-4 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-12 grid gap-1 md:col-span-3">
                    <label for="sort" class="text-[0.66rem] font-bold uppercase tracking-widest text-zinc-500">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="h-12 w-full rounded-2xl border border-zinc-300 bg-white px-4 text-sm text-zinc-800 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20">
                        <option value="created_at_desc" @selected($selectedSort === 'created_at_desc')>{{ __('Newest first') }}</option>
                        <option value="created_at_asc" @selected($selectedSort === 'created_at_asc')>{{ __('Oldest first') }}</option>
                        <option value="name_asc" @selected($selectedSort === 'name_asc')>{{ __('Name A-Z') }}</option>
                        <option value="name_desc" @selected($selectedSort === 'name_desc')>{{ __('Name Z-A') }}</option>
                        <option value="complexity_asc" @selected($selectedSort === 'complexity_asc')>{{ __('Difficulty low-high') }}</option>
                        <option value="complexity_desc" @selected($selectedSort === 'complexity_desc')>{{ __('Difficulty high-low') }}</option>
                    </select>
                </div>

                <div class="col-span-12 flex items-center gap-3 pt-2">
                    <a href="{{ route('recipes.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900">{{ __('Reset') }}</a>
                </div>
            </form>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 md:gap-5">
            @forelse ($recipes as $recipe)
                <article class="group overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-[0_18px_35px_-30px_rgba(15,23,42,0.75)] transition hover:-translate-y-0.5 hover:shadow-[0_26px_40px_-28px_rgba(15,23,42,0.75)]">
                    <a href="{{ route('recipes.show', $recipe) }}">
                        @if ($recipe->photos->isNotEmpty())
                            <div class="block aspect-video sm:aspect-square bg-linear-to-br from-zinc-200 to-zinc-50">
                                <img
                                    src="{{ $recipe->photos->first()->url() }}?v={{ $recipe->updated_at->timestamp }}"
                                    alt="{{ $recipe->name }}"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                >
                            </div>
                        @else
                            <div class="hidden sm:block aspect-video sm:aspect-square bg-linear-to-br from-zinc-200 to-zinc-50">
                                <div class="grid h-full place-items-center text-sm font-semibold text-zinc-400">{{ __('No image') }}</div>
                            </div>
                        @endif

                        <div class="grid gap-3 p-4 md:p-5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[0.68rem] font-bold uppercase tracking-[0.06em] text-zinc-500">{{ $recipe->category?->name }}</span>

                                @if ($recipe->cookbook)
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold leading-none text-zinc-700">{{ $recipe->cookbook->name }}</span>
                                @endif
                            </div>

                            <h2 class="text-lg font-semibold leading-tight text-zinc-900">{{ $recipe->name }}</h2>

                            <p class="min-h-[5.2rem] overflow-hidden text-sm leading-6 text-zinc-600 [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:4]">{{ \Illuminate\Support\Str::limit(strip_tags((string) $recipe->instructions), 170) }}</p>

                            <div class="flex flex-wrap items-center gap-1.5">
                                @if ($recipe->ratings_count > 0)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold leading-none text-amber-800">
                                        {{ str_repeat('★', (int) round((float) $recipe->ratings_avg_stars)) }}
                                        {{ __(':stars / :ratings', ['stars' => number_format((float) $recipe->ratings_avg_stars, 1), 'ratings' => $recipe->ratings_count]) }}
                                    </span>
                                @endif

                                <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold leading-none text-sky-800">{{ $recipe->complexity?->label() }}</span>

                                @if ($recipe->preparation_time)
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold leading-none text-zinc-700">{{ $recipe->preparation_time->format('H:i') }}</span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-3 text-xs text-zinc-500">
                                <span>{{ $recipe->author?->name }}</span>
                                <span>{{ $recipe->created_at?->isoFormat('L') }}</span>
                            </div>
                        </div>
                    </a>
                </article>
            @empty
                <p class="col-span-full rounded-2xl border border-zinc-200 bg-white p-4 text-center text-sm text-zinc-600">{{ __('No recipes found.') }}</p>
            @endforelse
        </section>

        @if ($recipes->hasPages())
            <nav class="grid grid-cols-1 items-center gap-3 pt-1 sm:grid-cols-[auto_1fr_auto]" aria-label="{{ __('Pagination') }}">
                <a
                    href="{{ $recipes->onFirstPage() ? '#' : $recipes->previousPageUrl() }}"
                    @class([
                        'inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-bold transition sm:justify-self-start',
                        'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900' => ! $recipes->onFirstPage(),
                        'pointer-events-none border-zinc-200 bg-zinc-100 text-zinc-400' => $recipes->onFirstPage(),
                    ])
                    style="min-width: 2.5rem; height: 2.5rem; padding-inline: 0.75rem;"
                >
                    {{ __('Previous') }}
                </a>

                <div class="flex flex-wrap items-center justify-center gap-1.5 sm:justify-self-center">
                    @foreach ($paginationPages as $page)
                        @if (is_null($page))
                            <span class="px-1 text-sm font-bold text-zinc-500">…</span>
                        @else
                            <a
                                href="{{ $recipes->url($page) }}"
                                @class([
                                    'inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-bold transition',
                                    'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900' => $page !== $currentPage,
                                    'border-cyan-600 bg-cyan-50 text-cyan-700' => $page === $currentPage,
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
                        'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900' => $recipes->hasMorePages(),
                        'pointer-events-none border-zinc-200 bg-zinc-100 text-zinc-400' => ! $recipes->hasMorePages(),
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
                    searchTimer = window.setTimeout(submitForm, 350);
                });
            }
        }());
    </script>
</body>
</html>
