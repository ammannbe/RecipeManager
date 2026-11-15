<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ __('Unsubscribe') }} · {{ config('app.name') }}</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 px-6 py-12">
        <h1 class="text-2xl font-semibold">
            {{ __('News by e-mail') }}
        </h1>

        @if ($done)
            <p data-unsubscribed>{{ __('You will no longer get news by e-mail from :app, also no maintenance notices.', ['app' => config('app.name')]) }}</p>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('You can turn them on again in your profile.') }}</p>
        @else
            <p>{{ __('Unsubscribe :email from news by e-mail (including maintenance notices)?', ['email' => $user->email]) }}</p>
            <form method="POST" action="{{ request()->fullUrl() }}">
                <button type="submit" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-violet-700">
                    {{ __('Unsubscribe') }}
                </button>
            </form>
        @endif

        <a href="{{ route('recipes.index') }}" class="text-sm text-violet-700 underline dark:text-violet-300">{{ config('app.name') }}</a>
    </main>
</body>
</html>
