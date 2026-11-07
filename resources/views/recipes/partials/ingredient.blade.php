@php($alternatives = $ingredient->ingredients)
@php($converter = app(\App\Services\UnitConverter::class))

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
        conversion: {{ \Illuminate\Support\Js::from($converter->pageConversion($ingredient)) }},
    }"
>
    <span class="font-bold" x-text="formatAmount(amount, amountMax, unit, conversion)"></span>
    {{ $ingredient->food?->name }}

    @if ($ingredient->ingredientAttributes->isNotEmpty())
        <span class="text-zinc-500 dark:text-zinc-400">({{ $ingredient->ingredientAttributes->pluck('name')->implode(', ') }})</span>
    @endif

    <span x-show="bracketAmount(amount, amountMax, unit, conversion)" x-cloak class="text-zinc-500 dark:text-zinc-400" x-text="bracketAmount(amount, amountMax, unit, conversion)"></span>
    <span x-show="cannotConvert(conversion)" x-cloak class="text-xs text-amber-700 dark:text-amber-400" title="{{ __('Without density or piece weight this amount cannot be converted.') }}" data-not-convertible>({{ __('not convertible') }})</span>

    @foreach ($alternatives as $alternative)
        <span
            class="block pl-4 text-zinc-500 dark:text-zinc-400"
            x-data="{
                amount: {{ \Illuminate\Support\Js::from($alternative->amount) }},
                amountMax: {{ \Illuminate\Support\Js::from($alternative->amount_max) }},
                unit: {{ \Illuminate\Support\Js::from($alternative->unit ? [
                    'name' => $alternative->unit->name,
                    'nameShortcut' => $alternative->unit->name_shortcut,
                    'namePlural' => $alternative->unit->name_plural,
                    'namePluralShortcut' => $alternative->unit->name_plural_shortcut,
                ] : null) }},
                conversion: {{ \Illuminate\Support\Js::from($converter->pageConversion($alternative)) }},
            }"
        >
            <em>{{ __('or') }}</em>
            <span class="font-bold" x-text="formatAmount(amount, amountMax, unit, conversion)"></span>
            {{ $alternative->food?->name }}

            @if ($alternative->ingredientAttributes->isNotEmpty())
                ({{ $alternative->ingredientAttributes->pluck('name')->implode(', ') }})
            @endif

            <span x-show="bracketAmount(amount, amountMax, unit, conversion)" x-cloak x-text="bracketAmount(amount, amountMax, unit, conversion)"></span>
            <span x-show="cannotConvert(conversion)" x-cloak class="text-xs text-amber-700 dark:text-amber-400" title="{{ __('Without density or piece weight this amount cannot be converted.') }}">({{ __('not convertible') }})</span>
        </span>
    @endforeach
</li>
