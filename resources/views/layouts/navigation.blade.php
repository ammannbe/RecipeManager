<flux:header class="border-b border-zinc-200 dark:border-zinc-700">
    <flux:brand
        href="{{ route('recipes.index') }}"
        wire:navigate
        logo="{{ asset('/favicon.ico') }}"
        name="{{ config('app.name') }}"
        alt="{{ config('app.name') }} Logo"
        class="px-2 me-0! hidden lg:flex"
    />

    <flux:sidebar.toggle class="lg:hidden mr-4" icon="bars-2" inset="left" />

    @if (isset($h1))
        <flux:heading level="1" size="xl" class="leading-6">
            {{ $h1 }}
        </flux:heading>
    @endif

    <flux:spacer />

    <flux:dropdown>
        @auth
            <flux:profile avatar:name="{{ author()->name }}" />
        @endauth

        @guest
            <flux:profile avatar:icon="user" />
        @endguest

        <flux:menu>
            @auth
                <flux:menu.item href="{{ route('settings.profile') }}" wire:navigate icon="user-circle">
                    {{ __('Profile') }}
                </flux:menu.item>
            @endauth

            <flux:menu.submenu heading="{{ __('Language') }}" icon="language">
                @foreach (config('app.locales') as $locale => $language)
                    <form action="{{ route('profile.locale', ['locale' => $locale]) }}" method="post">
                        @csrf
                        <flux:menu.item icon="{{ $locale }}" type="submit">
                            {{ $language }}
                        </flux:menu.item>
                    </form>
                @endforeach
            </flux:menu>

            <flux:menu.item icon="moon" x-data x-on:click="$flux.dark = ! $flux.dark" x-show="! $flux.dark">
                <span x-show="! $flux.dark">{{ __('Dark mode') }}</span>
            </flux:menu.item>

            <flux:menu.item icon="sun" x-data x-on:click="$flux.dark = ! $flux.dark" x-show="$flux.dark">
                <span x-show="$flux.dark">{{ __('Light mode') }}</span>
            </flux:menu.item>

            <flux:menu.separator />

            @auth
                @if (user()->admin)
                    <flux:menu.item icon="shield-exclamation" href="{{ route('recipes.index') }}" wire:navigate>
                        <span>{{ __('Admin') }}</span>
                    </flux:menu.item>
                @elseif (session()->get('admin_logged_in'))
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <flux:menu.item icon="shield-exclamation" variant="danger" type="submit">
                            {{ __('Admin logout') }}
                        </flux:menu.item>
                    </form>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:menu.item icon="arrow-right-start-on-rectangle" variant="danger" type="submit">
                        {{ __('Logout') }}
                    </flux:menu.item>
                </form>
            @endauth

            @guest
                <flux:menu.item href="{{ route('login') }}" wire:navigate icon="user-circle">
                    {{ __('Login') }}
                </flux:menu.item>
            @endguest
        </flux:menu>
    </flux:dropdown>
</flux:header>
