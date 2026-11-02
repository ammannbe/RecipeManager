<?php

namespace App\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Name of the OpenID Connect provider on the buttons: AUTH_OIDC_LABEL, otherwise the application
 * name a Pocket ID provider publishes (its APP_NAME), otherwise "SSO".
 */
class OidcLabel
{
    public const string FALLBACK = 'SSO';

    private const string CACHE_KEY = 'oidc.label';

    public static function get(): string
    {
        return once(function (): string {
            $label = config('login.oidc.label');

            if (is_string($label) && trim($label) !== '') {
                return trim($label);
            }

            $cached = Cache::get(self::CACHE_KEY);

            if (is_string($cached)) {
                return $cached;
            }

            $name = self::fromProvider();

            // A failed request is tried again after an hour, so a provider outage does not stick.
            Cache::put(self::CACHE_KEY, $name ?? self::FALLBACK, $name === null ? now()->addHour() : now()->addDay());

            return $name ?? self::FALLBACK;
        });
    }

    /**
     * Pocket ID lists its public settings at /api/application-configuration; other providers have no such endpoint.
     */
    private static function fromProvider(): ?string
    {
        $issuer = config('services.oidc.base_url');

        if (! is_string($issuer) || $issuer === '') {
            return null;
        }

        try {
            $settings = Http::timeout(3)->acceptJson()->get(rtrim($issuer, '/').'/api/application-configuration')->json();
        } catch (Throwable) {
            return null;
        }

        foreach (is_array($settings) ? $settings : [] as $setting) {
            if (is_array($setting) && ($setting['key'] ?? null) === 'appName' && is_string($setting['value'] ?? null)) {
                $name = trim($setting['value']);

                return $name !== '' ? mb_substr($name, 0, 50) : null;
            }
        }

        return null;
    }
}
