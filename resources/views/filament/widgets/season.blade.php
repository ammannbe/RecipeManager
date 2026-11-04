<x-filament-widgets::widget>
    <x-filament::section :heading="__('In season in :month', ['month' => $month])" icon="heroicon-o-sun">
        <x-slot name="afterHeader">
            <x-filament::link :href="route('recipes.season')" size="sm">{{ __('Season calendar') }}</x-filament::link>
        </x-slot>

        @if ($foods->isNotEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $foods->implode(', ') }}</p>
        @endif

        @if ($recipes->isNotEmpty())
            <ul class="mt-3 grid gap-1 text-sm">
                @foreach ($recipes as $recipe)
                    <li>
                        <x-filament::link :href="route('recipes.show', $recipe)" target="_blank">{{ $recipe->name }}</x-filament::link>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-gray-500">{{ __('No seasonal recipes this month yet.') }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
