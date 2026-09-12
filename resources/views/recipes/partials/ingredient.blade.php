@php($alternatives = $ingredient->ingredients)

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

    @if ($ingredient->ingredientAttributes->isNotEmpty())
        <span class="text-zinc-500 dark:text-zinc-400">({{ $ingredient->ingredientAttributes->pluck('name')->implode(', ') }})</span>
    @endif

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
            }"
        >
            <em>{{ __('or') }}</em>
            <span class="font-bold" x-text="formatAmount(amount, amountMax, unit)"></span>
            {{ $alternative->food?->name }}

            @if ($alternative->ingredientAttributes->isNotEmpty())
                ({{ $alternative->ingredientAttributes->pluck('name')->implode(', ') }})
            @endif
        </span>
    @endforeach
</li>
