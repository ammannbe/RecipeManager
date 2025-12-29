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

        <flux:main>
            {{ $slot }}
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
