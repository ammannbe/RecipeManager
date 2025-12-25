<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @if (! config('app.debug'))
            <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-NFHFWB99');</script>
            <!-- End Google Tag Manager -->
        @endif

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if (isset($title))
            <title>{{ $title . ' | ' . config('app.name') }}</title>
        @else
            <title>{{ config('app.name') }}</title>
        @endif

        @fluxAppearance
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="min-h-screen">
        @if (! config('app.debug'))
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NFHFWB99"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <!-- End Google Tag Manager (noscript) -->
        @endif

        <flux:main>
            <div class="fixed top-0 inset-x-0 w-full p-4 flex justify-between items-center">
                <flux:brand
                    href="{{ route('recipes.index') }}"
                    wire:navigate
                    logo="{{ asset('/favicon.ico') }}"
                    name="{{ config('app.name') }}"
                    alt="{{ config('app.name') }} Logo"
                    class="px-2 me-0!"
                />

                <flux:dropdown>
                    <flux:button variant="ghost" icon="language">
                        {{ __('Language') }}
                    </flux:button>

                    <flux:menu>
                        @foreach (config('app.locales') as $locale => $language)
                            <form action="{{ route('profile.locale', ['locale' => $locale]) }}" method="post">
                                @csrf
                                <flux:menu.item icon="{{ $locale }}" type="submit">
                                    {{ $language }}
                                </flux:menu.item>
                            </form>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </div>

            <div class="grid h-full place-items-center">
                {{ $slot }}
            </div>
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        @vite(['resources/js/app.js'])
        @livewireScripts

        @if (session('toast'))
            <script>
                document.addEventListener('livewire:navigated', () => {
                    Flux.toast(@js(session('toast')));
                });
            </script>
        @endif
    </body>
</html>
