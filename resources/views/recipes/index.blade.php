<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="recipes-body text-zinc-900">
    <nav class="recipes-nav sticky top-0 z-20">
        <div class="recipes-container recipes-nav-inner">
            <a href="{{ route('recipes.index') }}" class="recipes-brand">
                <img src="{{ asset('favicon.ico') }}" alt="{{ config('app.name') }}" class="h-8 w-8 rounded-lg">
                <span>{{ config('app.name') }}</span>
            </a>

            @guest
                <a href="{{ url('/admin/login') }}" class="recipes-top-button">{{ __('Login') }}</a>
            @endguest

            @auth
                <a href="{{ url('/admin') }}" class="recipes-top-button">{{ __('Backend') }}</a>
            @endauth
        </div>
    </nav>

    <main class="recipes-container recipes-main">
        <header class="recipes-page-header">
            <h1 class="recipes-page-title">{{ __('Recipes') }}</h1>
            <p class="recipes-page-subtitle">{{ __('Public recipes and your private cookbook recipes in one place.') }}</p>
        </header>

        <section class="recipes-filter-shell">
            <form method="GET" action="{{ route('recipes.index') }}" class="recipes-form-grid" data-auto-submit-filters>
                <div class="recipes-filter-toolbar md:col-span-12">
                    <div class="recipes-filter-group">
                        <p class="recipes-field-label">{{ __('Quick') }}</p>

                        <label class="recipes-chip recipes-chip-option {{ $quick ? 'recipes-chip-active' : '' }}">
                            <input type="checkbox" name="quick" value="1" class="recipes-hidden-input" @checked($quick)>
                            {{ __('Max. 30 min') }}
                        </label>
                    </div>

                    <div class="recipes-filter-group recipes-filter-group-divider">
                        <p class="recipes-field-label">{{ __('Difficulty') }}</p>

                        @php($complexityOptions = ['' => __('All'), 'simple' => __('Simple'), 'normal' => __('Normal'), 'difficult' => __('Difficult')])
                        @foreach ($complexityOptions as $value => $label)
                            <label class="recipes-chip recipes-chip-option {{ $complexity === $value ? 'recipes-chip-active' : '' }}">
                                <input
                                    type="radio"
                                    name="complexity"
                                    value="{{ $value }}"
                                    class="recipes-hidden-input"
                                    @checked($complexity === $value)
                                >
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="recipes-field recipes-field-search">
                    <label for="search" class="recipes-field-label">{{ __('Search') }}</label>
                    <input
                        id="search"
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="{{ __('Search for recipes...') }}"
                        class="recipes-input"
                    >
                </div>

                <div class="recipes-field recipes-field-category">
                    <label for="category" class="recipes-field-label">{{ __('Category') }}</label>
                    <select id="category" name="category" class="recipes-input">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="recipes-field recipes-field-sort">
                    <label for="sort" class="recipes-field-label">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="recipes-input">
                        <option value="created_at_desc" @selected($selectedSort === 'created_at_desc')>{{ __('Newest first') }}</option>
                        <option value="created_at_asc" @selected($selectedSort === 'created_at_asc')>{{ __('Oldest first') }}</option>
                        <option value="name_asc" @selected($selectedSort === 'name_asc')>{{ __('Name A-Z') }}</option>
                        <option value="name_desc" @selected($selectedSort === 'name_desc')>{{ __('Name Z-A') }}</option>
                        <option value="complexity_asc" @selected($selectedSort === 'complexity_asc')>{{ __('Difficulty low-high') }}</option>
                        <option value="complexity_desc" @selected($selectedSort === 'complexity_desc')>{{ __('Difficulty high-low') }}</option>
                    </select>
                </div>

                <div class="recipes-actions">
                    <a href="{{ route('recipes.index') }}" class="recipes-secondary-button">{{ __('Reset') }}</a>
                </div>
            </form>
        </section>

        <section class="recipes-grid">
            @forelse ($recipes as $recipe)
                <article class="recipe-card">
                    <a href="{{ route('recipes.show', $recipe) }}" class="recipe-card-media block">
                        @if ($recipe->photos->isNotEmpty())
                            <img
                                src="{{ $recipe->photos->first()->url() }}?v={{ $recipe->updated_at->timestamp }}"
                                alt="{{ $recipe->name }}"
                                class="h-full! w-full object-cover transition-transform duration-300 hover:scale-105"
                            >
                        @else
                            <div class="recipe-card-placeholder">{{ __('No image') }}</div>
                        @endif
                    </a>

                    <div class="recipe-card-content">
                        <div class="recipe-card-head">
                            <span class="recipe-card-category">{{ $recipe->category?->name }}</span>

                            @if ($recipe->cookbook)
                                <span class="recipe-label recipe-label-cookbook">{{ $recipe->cookbook->name }}</span>
                            @endif
                        </div>

                        <h2 class="recipe-card-title">
                            <a href="{{ route('recipes.show', $recipe) }}" class="hover:underline">{{ $recipe->name }}</a>
                        </h2>

                        <p class="recipe-card-excerpt">{{ \Illuminate\Support\Str::limit(strip_tags((string) $recipe->instructions), 170) }}</p>

                        <div class="recipe-card-labels">
                            @if ($recipe->ratings_count > 0)
                                <span class="recipe-label recipe-label-rating">
                                    {{ str_repeat('★', (int) round((float) $recipe->ratings_avg_stars)) }}
                                    {{ __(':stars / :ratings', ['stars' => number_format((float) $recipe->ratings_avg_stars, 1), 'ratings' => $recipe->ratings_count]) }}
                                </span>
                            @endif

                            <span class="recipe-label recipe-label-complexity">{{ $recipe->complexity?->label() }}</span>

                            @if ($recipe->preparation_time)
                                <span class="recipe-label recipe-label-time">{{ $recipe->preparation_time->format('H:i') }}</span>
                            @endif
                        </div>

                        <div class="recipe-card-meta">
                            <span>{{ $recipe->author?->name }}</span>
                            <span>{{ $recipe->created_at?->isoFormat('L') }}</span>
                        </div>
                    </div>
                </article>
            @empty
                <p class="recipes-empty">{{ __('No recipes found.') }}</p>
            @endforelse
        </section>

        @if ($recipes->hasPages())
            <nav class="recipes-pagination" aria-label="{{ __('Pagination') }}">
                <a
                    href="{{ $recipes->onFirstPage() ? '#' : $recipes->previousPageUrl() }}"
                    class="recipes-page-link {{ $recipes->onFirstPage() ? 'recipes-page-link-disabled' : '' }}"
                >
                    {{ __('Previous') }}
                </a>

                <div class="recipes-page-numbers">
                    @foreach ($paginationPages as $page)
                        @if (is_null($page))
                            <span class="recipes-page-ellipsis">…</span>
                        @else
                            <a
                                href="{{ $recipes->url($page) }}"
                                class="recipes-page-link {{ $page === $currentPage ? 'recipes-page-link-active' : '' }}"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                </div>

                <a
                    href="{{ $recipes->hasMorePages() ? $recipes->nextPageUrl() : '#' }}"
                    class="recipes-page-link {{ $recipes->hasMorePages() ? '' : 'recipes-page-link-disabled' }}"
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
