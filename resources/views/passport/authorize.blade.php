<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Authorize :client', ['client' => $client->name]) }} - {{ config('app.name') }}</title>
    @include('partials.favicon')
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
            <img src="{{ asset('favicon-96x96.png') }}" alt="{{ config('app.name') }}" class="h-8 w-8 rounded-lg">
            <h1 class="text-xl font-bold tracking-tight">{{ __('Authorize :client', ['client' => $client->name]) }}</h1>
        </header>

        @php($isMcp = collect($scopes)->contains(fn ($scope) => $scope->id === \Laravel\Mcp\Server\Registrar::OAUTH_SCOPE))

        @if ($isMcp)
            <p class="text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                {{ __(':client is an AI client (MCP) and wants to access your :app account (:email). Choose what it may do; you can change or revoke it later under "Connected AI clients".', [
                    'client' => $client->name,
                    'app' => config('app.name'),
                    'email' => $user->email,
                ]) }}
            </p>

            <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                {{ __('It returns to: :host', ['host' => parse_url((string) $request->query('redirect_uri'), PHP_URL_HOST) ?: (string) $request->query('redirect_uri')]) }}
            </p>
        @else
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
        @endif

        @if ($isMcp)
            <fieldset class="grid gap-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <legend class="px-1 text-sm font-semibold">{{ __('Permissions') }}</legend>
                @foreach (\App\Mcp\McpPermission::cases() as $permission)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" form="approve" name="permissions[]" value="{{ $permission->value }}" @checked(in_array($permission->value, old('permissions', ['read']), true)) class="h-4 w-4 rounded border-zinc-300 text-violet-600 focus:ring-violet-500">
                        {{ $permission->label() }}
                    </label>
                @endforeach
                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Only recipes you may see or edit yourself. Deleted recipes go to the trash.') }}</p>
                @error('permissions')
                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </fieldset>
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

            <form method="post" action="{{ $isMcp ? route('mcp.authorize.approve') : route('passport.authorizations.approve') }}" @if ($isMcp) id="approve" @endif>
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
