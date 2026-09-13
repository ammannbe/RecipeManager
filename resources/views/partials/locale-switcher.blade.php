@php($locales = config('app.locales', []))

@if (count($locales) > 1)
    <div class="inline-flex items-center overflow-hidden rounded-xl border border-zinc-300 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        @foreach ($locales as $code => $label)
            @php($isCurrent = app()->getLocale() === $code)

            <a
                href="{{ route('profile.locale', $code) }}"
                @class([
                    'px-2.5 py-2 text-xs font-bold uppercase leading-none transition',
                    'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400' => $isCurrent,
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
