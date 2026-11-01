<?php

use App\Http\Middleware\SetLocale;
use App\Models\OAuthClient;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Guests hitting /oauth/authorize log in through Filament and come back afterwards. Trusted
        // companion apps go straight to the OIDC login: no input when already logged in there.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('oauth/authorize')
            && config('login.oidc.enabled')
            && OAuthClient::isTrusted($request->query('client_id'))
                ? route('oidc.redirect')
                : route('filament.app.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients get JSON errors even without an "Accept: application/json" header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
