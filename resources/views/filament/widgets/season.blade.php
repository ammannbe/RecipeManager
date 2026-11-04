{{-- The panel has no Tailwind utilities of its own, so spacing uses inline styles. --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('In season in :month', ['month' => $month])" icon="heroicon-o-sun">
        <x-slot name="afterHeader">
            <x-filament::link :href="route('recipes.season')" size="sm">{{ __('Season calendar') }}</x-filament::link>
        </x-slot>

        @if ($foods->isNotEmpty())
            <p style="font-size: 0.875rem; opacity: 0.75; margin: 0 0 0.75rem;">{{ $foods->implode(', ') }}</p>
        @endif

        @if ($recipes->isNotEmpty())
            <ul style="display: grid; gap: 0.25rem; margin: 0; padding: 0; list-style: none;">
                @foreach ($recipes as $recipe)
                    <li>
                        <x-filament::link :href="route('recipes.show', $recipe)" target="_blank">{{ $recipe->name }}</x-filament::link>
                    </li>
                @endforeach
            </ul>
        @else
            <p style="font-size: 0.875rem; opacity: 0.75; margin: 0;">{{ __('No seasonal recipes this month yet.') }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
