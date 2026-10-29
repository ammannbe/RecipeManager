<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Authorize :client', ['client' => $client->name]) }} - {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <script>
        (function () {
            var theme = localStorage.getItem('theme');

            if (theme === 'dark' || (! theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }());
    </script>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-zinc-50 px-6 py-10 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <main class="grid w-full max-w-md gap-5 rounded-2xl border border-zinc-200 bg-white p-6 shadow-[0_18px_35px_-30px_rgba(15,23,42,0.75)] dark:border-zinc-800 dark:bg-zinc-900">
        <header class="flex items-center gap-3">
            <img src="{{ asset('favicon.ico') }}" alt="{{ config('app.name') }}" class="h-8 w-8 rounded-lg">
            <h1 class="text-xl font-bold tracking-tight">{{ __('Authorize :client', ['client' => $client->name]) }}</h1>
        </header>

        <p class="text-sm leading-6 text-zinc-700 dark:text-zinc-300">
            {{ __(':client wants to access your :app account (:email). It will be able to read the recipes you can see and save new private recipes for you.', [
                'client' => $client->name,
                'app' => config('app.name'),
                'email' => $user->email,
            ]) }}
        </p>

        @if (count($scopes) > 0)
            <ul class="list-disc pl-5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                @foreach ($scopes as $scope)
                    <li>{{ __($scope->description) }}</li>
                @endforeach
            </ul>
        @endif

        <div class="flex flex-wrap justify-end gap-2">
            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    {{ __('Deny') }}
                </button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="inline-flex items-center rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600">
                    {{ __('Authorize') }}
                </button>
            </form>
        </div>
    </main>
</body>
</html>
