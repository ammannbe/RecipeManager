<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $recipe->name }} - {{ config('app.name') }}</title>
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
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('recipes.index') }}" class="recipes-back-link">{{ __('Back to recipes') }}</a>

            @if (user() && (user()->admin || user()->author_id === $recipe->author_id))
                <a
                    href="{{ \App\Filament\Resources\Recipes\RecipeResource::getUrl('edit', ['record' => $recipe]) }}"
                    class="recipes-top-button"
                >
                    {{ __('Edit') }}
                </a>
            @endif
        </div>

        <article class="recipes-detail-shell">
            <header class="recipes-detail-header">
                <h1 class="recipes-page-title">{{ $recipe->name }}</h1>

                <div class="recipes-detail-meta">
                    <span>{{ $recipe->author?->name }}</span>
                    <span>•</span>
                    <span>{{ $recipe->category?->name }}</span>
                    @if ($recipe->cookbook)
                        <span>•</span>
                        <span>{{ $recipe->cookbook->name }}</span>
                    @endif
                </div>
            </header>

            @php($photoUrls = $recipe->photos->map(fn ($photo) => $photo->url().'?v='.$recipe->updated_at->timestamp)->values())

            @if ($photoUrls->count() > 1)
                <div
                    x-data="{
                        images: {{ \Illuminate\Support\Js::from($photoUrls) }},
                        current: 0,
                        touchStartX: null,
                        next() {
                            this.current = (this.current + 1) % this.images.length;
                        },
                        prev() {
                            this.current = (this.current - 1 + this.images.length) % this.images.length;
                        },
                        onTouchStart(event) {
                            this.touchStartX = event.changedTouches[0].clientX;
                        },
                        onTouchEnd(event) {
                            if (this.touchStartX === null) {
                                return;
                            }

                            const deltaX = event.changedTouches[0].clientX - this.touchStartX;
                            this.touchStartX = null;

                            if (Math.abs(deltaX) < 40) {
                                return;
                            }

                            if (deltaX < 0) {
                                this.next();
                                return;
                            }

                            this.prev();
                        },
                    }"
                    class="recipes-slideshow"
                >
                    <div
                        class="recipes-slideshow-frame"
                        @touchstart.passive="onTouchStart($event)"
                        @touchend.passive="onTouchEnd($event)"
                    >
                        <img
                            :src="images[current]"
                            alt="{{ $recipe->name }}"
                            class="recipes-detail-image recipes-slideshow-image"
                        >

                        <button
                            type="button"
                            class="recipes-slideshow-control recipes-slideshow-control-prev"
                            @click="prev()"
                            aria-label="{{ __('Previous image') }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="recipes-slideshow-control-icon">
                                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="recipes-slideshow-control recipes-slideshow-control-next"
                            @click="next()"
                            aria-label="{{ __('Next image') }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="recipes-slideshow-control-icon">
                                <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>

                    <div class="recipes-slideshow-dots" role="tablist" aria-label="{{ __('Recipe images') }}">
                        <template x-for="(image, index) in images" :key="`dot-${index}`">
                            <button
                                type="button"
                                class="recipes-slideshow-dot"
                                :class="{ 'is-active': current === index }"
                                @click="current = index"
                                :aria-label="`{{ __('Image') }} ${index + 1}`"
                            ></button>
                        </template>
                    </div>
                </div>
            @elseif ($photoUrls->isNotEmpty())
                <img
                    src="{{ $photoUrls->first() }}"
                    alt="{{ $recipe->name }}"
                    class="recipes-detail-image"
                >
            @endif

            <div class="recipes-detail-grid">
                <section class="recipes-detail-card">
                    <h2 class="recipes-section-title">{{ __('Ingredients') }}</h2>

                    <div class="space-y-5">
                        @php($ungroupedIngredients = $recipe->ingredients->whereNull('ingredient_group_id'))

                        @if ($ungroupedIngredients->isNotEmpty())
                            <ul class="recipes-ingredient-list">
                                @foreach ($ungroupedIngredients->filter(fn ($ingredient) => ! $ingredient->ingredient_id) as $ingredient)
                                    <li>
                                        <span class="recipes-ingredient-amount">{{ $ingredient->getAmountAndUnit() }}</span>
                                        {{ $ingredient->food?->name }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @foreach ($recipe->ingredientGroups as $group)
                            @php($ingredients = $recipe->ingredients->where('ingredient_group_id', $group->id))

                            <div>
                                <h3 class="recipes-group-title">
                                    {{ $group->name }}
                                </h3>

                                <ul class="recipes-ingredient-list">
                                    @foreach ($ingredients->filter(fn ($ingredient) => ! $ingredient->ingredient_id) as $ingredient)
                                        <li>
                                            <span class="recipes-ingredient-amount">{{ $ingredient->getAmountAndUnit() }}</span>
                                            {{ $ingredient->food?->name }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="recipes-detail-card">
                    <h2 class="recipes-section-title">{{ __('Preparation') }}</h2>
                    <div class="recipe-instructions">
                        {!! $recipe->instructions !!}
                    </div>
                </section>
            </div>
        </article>
    </main>
</body>
</html>
