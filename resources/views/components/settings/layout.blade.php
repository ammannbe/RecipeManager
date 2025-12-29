<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-55">
        <flux:navlist>
            <flux:navlist.item :href="route('settings.profile')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            @if (user()->admin)
                <flux:navlist.item :href="route('settings.authors')" wire:navigate>{{ __('Authors') }}</flux:navlist.item>
            @endif
            <flux:navlist.item :href="route('settings.recipes')" wire:navigate>{{ __('Recipes') }}</flux:navlist.item>
            <flux:navlist.item :href="route('settings.password')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </div>
</div>
