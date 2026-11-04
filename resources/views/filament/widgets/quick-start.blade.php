{{-- The panel has no Tailwind utilities of its own, so the layout uses inline styles. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
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
