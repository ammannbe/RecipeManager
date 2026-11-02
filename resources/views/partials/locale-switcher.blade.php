@php($locales = config('app.locales', []))
@php($large = $large ?? false)

@if (count($locales) > 1)
    <div @class([
        'inline-flex items-center overflow-hidden rounded-xl border border-zinc-300 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900',
        'h-9' => ! $large,
        'h-11' => $large,
    ])>
        @foreach ($locales as $code => $label)
            @php($isCurrent = app()->getLocale() === $code)

            <a
                href="{{ route('profile.locale', $code) }}"
                @class([
                    'inline-flex h-full items-center text-xs font-bold uppercase leading-none transition focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-violet-600',
                    'px-2.5' => ! $large,
                    'px-4' => $large,
                    'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400' => $isCurrent,
                    'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! $isCurrent,
                ])
                title="{{ $label }}"
                @if ($isCurrent) aria-current="true" @endif
            >
                {{ $code }}
            </a>
        @endforeach
    </div>
@endif
