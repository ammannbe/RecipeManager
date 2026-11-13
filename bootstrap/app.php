<?php

use App\Http\Middleware\EnsureEntitlementAccess;
use App\Http\Middleware\SetLocale;
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
            // ENTITLEMENTS_DRIVER=account-manager: signs out users whose access was blocked in the AccountManager.
            EnsureEntitlementAccess::class,
        ]);

        // Guests hitting /oauth/authorize log in through Filament and come back afterwards. Companion
        // apps get the login page too, never the OIDC provider directly: accounts that are not linked
        // sign in with their password there instead of being linked by e-mail (App\Filament\Auth\Login).
        $middleware->redirectGuestsTo(fn (): string => route('filament.app.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients get JSON errors even without an "Accept: application/json" header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
