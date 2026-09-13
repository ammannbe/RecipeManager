@php($locales = config('app.locales', []))

@if (count($locales) > 1)
    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            <x-filament::icon-button
                icon="heroicon-o-language"
                :label="__('Language')"
                :tooltip="__('Language')"
                color="gray"
            />
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($locales as $code => $label)
                <x-filament::dropdown.list.item
                    :href="route('profile.locale', $code)"
                    tag="a"
                    :icon="app()->getLocale() === $code ? 'heroicon-m-check' : null"
                >
                    {{ $label }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
