<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
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
        @include('layouts.navigation')

        <flux:main
            x-data="{ showScrollToTop: false }"
            @scroll.window="showScrollToTop = (window.pageYOffset > 150) ? true : false"
        >
            @if (user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! user()->hasVerifiedEmail())
                <x-card class="bg-amber-400/80! dark:bg-amber-800/80! p-4 lg:px-8 mb-6 lg:mb-8 rounded-lg">
                    <form method="post" action="{{ route('verification.send') }}">
                        @csrf

                        <flux:text variant="strong">
                            {{ __('Please verify your email address.') }}

                            <button type="submit" class="cursor-pointer underline text-sm rounded focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </flux:text>
                    </form>
                </x-card>
            @endif

            @if (isset($header))
                {{ $header }}

                <flux:separator variant="subtle" class="mt-4 mb-6" />
            @endif

            {{ $slot }}

            <div
                @click="window.scrollTo({top: 0, behavior: 'smooth'})"
                x-show="showScrollToTop"
                class="fixed bottom-4 right-4"
                style="display: none;"
            >
                <flux:button icon="arrow-up" />
            </div>
        </flux:main>

        @if (isset($endbody))
            {{ $endbody }}
        @endif

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
                document.addEventListener('livewire:initialized', () => {
                    Flux.toast(@js(session('toast')));
                });
            </script>
        @endif
    </body>
</html>
