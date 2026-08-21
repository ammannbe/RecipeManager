<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $recipe->name }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900">
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-0 h-112 w-160 rounded-full bg-[radial-gradient(circle,rgba(251,191,36,0.22),transparent_70%)] blur-3xl"></div>
        <div class="absolute -top-44 right-0 h-112 w-xl rounded-full bg-[radial-gradient(circle,rgba(59,130,246,0.16),transparent_72%)] blur-3xl"></div>
    </div>

    <nav class="sticky top-0 z-20 border-b border-zinc-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-4">
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

    <main class="mx-auto grid w-full max-w-5xl gap-6 px-6 py-8 pb-32 lg:gap-8 lg:py-10">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('recipes.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-zinc-600 transition hover:text-zinc-900">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                    <path d="M19 12H5M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                {{ __('Back to recipes') }}
            </a>

            @if (user() && (user()->admin || user()->author_id === $recipe->author_id))
                <a
                    href="{{ \App\Filament\Resources\Recipes\RecipeResource::getUrl('edit', ['record' => $recipe]) }}"
                    class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900"
                >
                    {{ __('Edit') }}
                </a>
            @endif
        </div>

        <article
            class="grid gap-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-[0_18px_35px_-30px_rgba(15,23,42,0.75)] md:gap-6 md:p-6"
            x-data="recipeServings({ initial: {{ $recipe->servings ?? 'null' }} })"
        >
            <header class="grid gap-2">
            <h1 class="text-[clamp(2rem,1.5rem+1.4vw,2.75rem)] font-extrabold leading-none tracking-[-0.03em] text-zinc-900">{{ $recipe->name }}</h1>

                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-zinc-600">
                    <span>{{ $recipe->author?->name }}</span>
                    @if ($recipe->category)
                        <span>•</span>
                        <a
                            href="{{ route('recipes.index', ['category' => $recipe->category->id]) }}"
                            class="underline decoration-dotted transition hover:text-zinc-900"
                            title="{{ __('View all recipes in this category') }}"
                        >{{ $recipe->category->name }}</a>
                    @endif
                    @if ($recipe->cookbook)
                        <span>•</span>
                        <span>{{ $recipe->cookbook->name }}</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    @if ($recipe->servings)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 py-1 pl-2.5 pr-1 text-xs font-semibold leading-none text-zinc-700">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3.5 w-3.5">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <button
                                type="button"
                                class="inline-flex h-4 w-4 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-900 disabled:pointer-events-none disabled:opacity-30"
                                @click="decrease()"
                                :disabled="servings <= 1"
                                aria-label="{{ __('Decrease servings') }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3 w-3">
                                    <path d="M5 12h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                                </svg>
                            </button>

                            <span x-text="formatServings()"></span>{{ $recipe->serving_type ? ' ' . $recipe->serving_type : '' }}

                            <button
                                type="button"
                                class="inline-flex h-4 w-4 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-900"
                                @click="increase()"
                                aria-label="{{ __('Increase servings') }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3 w-3">
                                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                                </svg>
                            </button>
                        </span>
                    @endif

                    @if ($recipe->complexity)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold leading-none text-sky-800">
                            @switch($recipe->complexity)
                                @case(\App\Enums\Complexity::Simple)
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3.5 w-3.5">
                                        <rect x="3" y="14" width="4" height="7" rx="1" fill="currentColor" />
                                    </svg>
                                    @break
                                @case(\App\Enums\Complexity::Normal)
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3.5 w-3.5">
                                        <rect x="3" y="14" width="4" height="7" rx="1" fill="currentColor" />
                                        <rect x="10" y="9" width="4" height="12" rx="1" fill="currentColor" />
                                    </svg>
                                    @break
                                @case(\App\Enums\Complexity::Difficult)
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3.5 w-3.5">
                                        <rect x="3" y="14" width="4" height="7" rx="1" fill="currentColor" />
                                        <rect x="10" y="9" width="4" height="12" rx="1" fill="currentColor" />
                                        <rect x="17" y="4" width="4" height="17" rx="1" fill="currentColor" />
                                    </svg>
                                    @break
                            @endswitch
                            {{ $recipe->complexity->label() }}
                        </span>
                    @endif

                    @if ($recipe->preparation_time)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold leading-none text-zinc-700">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3.5 w-3.5">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
                                <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ $recipe->preparation_time->format('H:i') }}
                        </span>
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
                    class="grid gap-3"
                >
                    <div
                        class="relative aspect-16/10 overflow-hidden rounded-xl touch-pan-y"
                        @touchstart.passive="onTouchStart($event)"
                        @touchend.passive="onTouchEnd($event)"
                    >
                        <img
                            :src="images[current]"
                            alt="{{ $recipe->name }}"
                            class="h-full w-full object-cover"
                        >

                        <button
                            type="button"
                            class="absolute left-3 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/80 bg-zinc-900/65 text-white backdrop-blur-[3px] transition hover:bg-zinc-900/80 active:scale-95"
                            @click="prev()"
                            aria-label="{{ __('Previous image') }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="absolute right-3 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/80 bg-zinc-900/65 text-white backdrop-blur-[3px] transition hover:bg-zinc-900/80 active:scale-95"
                            @click="next()"
                            aria-label="{{ __('Next image') }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                                <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-wrap justify-center gap-1.5" role="tablist" aria-label="{{ __('Recipe images') }}">
                        <template x-for="(image, index) in images" :key="`dot-${index}`">
                            <button
                                type="button"
                                class="h-2.5 w-2.5 rounded-full bg-zinc-300 transition"
                                :class="{ 'scale-110 bg-zinc-800': current === index }"
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
                    class="max-h-112 w-full rounded-xl object-cover"
                >
            @endif

            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)] md:gap-5">
                <section class="rounded-xl border border-zinc-200 bg-white p-5 md:p-6">
                    <h2 class="mb-3 text-[1.1rem] font-semibold leading-tight text-zinc-900">{{ __('Ingredients') }}</h2>

                    <p
                        x-show="changed"
                        x-transition
                        x-cloak
                        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800"
                    >
                        {{ __('Servings amounts are recalculated automatically and may not be perfectly accurate.') }}
                    </p>

                    <div class="space-y-5">
                        @php($ungroupedIngredients = $recipe->ingredients->whereNull('ingredient_group_id'))

                        @if ($ungroupedIngredients->isNotEmpty())
                            <ul class="grid gap-1 text-sm leading-6 text-zinc-700">
                                @foreach ($ungroupedIngredients->filter(fn ($ingredient) => ! $ingredient->ingredient_id) as $ingredient)
                                    <li
                                        x-data="{
                                            amount: {{ \Illuminate\Support\Js::from($ingredient->amount) }},
                                            amountMax: {{ \Illuminate\Support\Js::from($ingredient->amount_max) }},
                                            unit: {{ \Illuminate\Support\Js::from($ingredient->unit ? [
                                                'name' => $ingredient->unit->name,
                                                'nameShortcut' => $ingredient->unit->name_shortcut,
                                                'namePlural' => $ingredient->unit->name_plural,
                                                'namePluralShortcut' => $ingredient->unit->name_plural_shortcut,
                                            ] : null) }},
                                        }"
                                    >
                                        <span class="font-bold" x-text="formatAmount(amount, amountMax, unit)"></span>
                                        {{ $ingredient->food?->name }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @foreach ($recipe->ingredientGroups as $group)
                            @php($ingredients = $recipe->ingredients->where('ingredient_group_id', $group->id))

                            <div>
                                <h3 class="mb-2 text-[0.95rem] font-semibold leading-tight text-zinc-700">
                                    {{ $group->name }}
                                </h3>

                                <ul class="grid gap-1 text-sm leading-6 text-zinc-700">
                                    @foreach ($ingredients->filter(fn ($ingredient) => ! $ingredient->ingredient_id) as $ingredient)
                                        <li
                                            x-data="{
                                                amount: {{ \Illuminate\Support\Js::from($ingredient->amount) }},
                                                amountMax: {{ \Illuminate\Support\Js::from($ingredient->amount_max) }},
                                                unit: {{ \Illuminate\Support\Js::from($ingredient->unit ? [
                                                    'name' => $ingredient->unit->name,
                                                    'nameShortcut' => $ingredient->unit->name_shortcut,
                                                    'namePlural' => $ingredient->unit->name_plural,
                                                    'namePluralShortcut' => $ingredient->unit->name_plural_shortcut,
                                                ] : null) }},
                                            }"
                                        >
                                            <span class="font-bold" x-text="formatAmount(amount, amountMax, unit)"></span>
                                            {{ $ingredient->food?->name }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-xl border border-zinc-200 bg-white p-5 md:p-6">
                    <h2 class="mb-3 text-[1.1rem] font-semibold leading-tight text-zinc-900">{{ __('Preparation') }}</h2>
                    <div class="text-sm leading-7 text-zinc-700 [&_blockquote]:mb-3 [&_blockquote]:text-zinc-700 [&_h1]:mb-3 [&_h1]:mt-4 [&_h1]:font-bold [&_h1]:text-zinc-900 [&_h2]:mb-3 [&_h2]:mt-4 [&_h2]:font-bold [&_h2]:text-zinc-900 [&_h3]:mb-3 [&_h3]:mt-4 [&_h3]:font-bold [&_h3]:text-zinc-900 [&_ol]:mb-3 [&_ol]:ml-4 [&_ol]:list-decimal [&_ol]:space-y-2 [&_p]:mb-3 [&_ul]:mb-3 [&_ul]:ml-4 [&_ul]:list-disc [&_ul]:space-y-2">
                        {!! $recipe->instructions !!}
                    </div>
                </section>
            </div>
        </article>
    </main>
</body>
</html>
