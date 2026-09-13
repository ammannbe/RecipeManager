<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Applies the locale a user picked, falling back to the session, then the
     * browser's preference, then the application default.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve($request);

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private static function resolve(Request $request): ?string
    {
        $candidates = [
            $request->user()?->locale,
            $request->session()->get('locale'),
            $request->getPreferredLanguage(array_keys(self::available())),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && self::isAvailable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function isAvailable(string $locale): bool
    {
        return array_key_exists($locale, self::available());
    }

    /**
     * @return array<string, string>
     */
    public static function available(): array
    {
        /** @var array<string, string> $locales */
        $locales = config('app.locales', []);

        return $locales;
    }
}
