<article class="group overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-[0_18px_35px_-30px_rgba(15,23,42,0.75)] transition hover:-translate-y-0.5 hover:shadow-[0_26px_40px_-28px_rgba(15,23,42,0.75)] dark:border-zinc-800 dark:bg-zinc-900">
    <a href="{{ route('recipes.show', $recipe) }}">
        @if ($recipe->photos->isNotEmpty())
            <div class="block aspect-video sm:aspect-square overflow-hidden bg-linear-to-br from-zinc-200 to-zinc-50 dark:from-zinc-800 dark:to-zinc-900">
                <img
                    src="{{ $recipe->photos->first()->url() }}?v={{ $recipe->updated_at->timestamp }}"
                    alt="{{ $recipe->name }}"
                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                >
            </div>
        @else
            <div class="hidden sm:block aspect-video sm:aspect-square overflow-hidden bg-linear-to-br from-zinc-200 to-zinc-50 dark:from-zinc-800 dark:to-zinc-900">
                <div class="grid h-full place-items-center text-sm font-semibold text-zinc-400 dark:text-zinc-600">{{ __('No image') }}</div>
            </div>
        @endif

        <div class="grid gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[0.68rem] font-bold uppercase tracking-[0.06em] text-zinc-500 dark:text-zinc-400">{{ $recipe->category?->name }}</span>

                @if ($recipe->cookbook)
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold leading-none text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $recipe->cookbook->name }}</span>
                @endif
            </div>

            <h2 class="text-lg font-semibold leading-tight text-zinc-900 dark:text-zinc-100">{{ $recipe->name }}</h2>

            <p class="min-h-[5.2rem] overflow-hidden text-sm leading-6 text-zinc-600 [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:4] dark:text-zinc-400">{{ \Illuminate\Support\Str::limit(strip_tags((string) $recipe->instructions), 170) }}</p>

            <div class="flex flex-wrap items-center gap-1.5">
                @if ($recipe->isSeasonal())
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold leading-none text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400" data-seasonal-badge>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-3.5 w-3.5">
                            <path d="M11 20A7 7 0 0 1 4 13c0-5 4-9 16-9 0 12-4 16-9 16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4 20c4-4 7-7 11-10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        {{ __('Seasonal') }}
                    </span>
                @endif

                <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold leading-none text-sky-800 dark:bg-sky-500/15 dark:text-sky-400">{{ $recipe->complexity?->label() }}</span>

                @if ($recipe->preparation_time)
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold leading-none text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $recipe->preparation_time->format('H:i') }}</span>
                @endif
            </div>

            <div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-500">
                <span>{{ $recipe->author?->name }}</span>
                <span>{{ $recipe->created_at?->isoFormat('L') }}</span>
            </div>
        </div>
    </a>
</article>
