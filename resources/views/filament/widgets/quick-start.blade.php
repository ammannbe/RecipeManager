<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-wrap gap-2">
            @foreach ($links as $link)
                <x-filament::button
                    tag="a"
                    :href="$link['url']"
                    :icon="$link['icon']"
                    :color="$link['primary'] ? 'primary' : 'gray'"
                    :outlined="! $link['primary']"
                >
                    {{ $link['label'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
