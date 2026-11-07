<?php

namespace App\Households;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Name of the AccountManager in the household texts: ACCOUNT_MANAGER_LABEL, otherwise the name the AccountManager
 * publishes at the public ACCOUNT_MANAGER_URL/api/info (its APP_NAME), otherwise "Account". Same idea as App\Auth\OidcLabel.
 */
class AccountManagerLabel
{
    private const string CACHE_KEY = 'account-manager.info';

    public static function get(): string
    {
        return once(function (): string {
            $label = config('households.account_manager.label');

            if (is_string($label) && trim($label) !== '') {
                return trim($label);
            }

            return self::info()['name'] ?? __('Account');
        });
    }

    /**
     * Households page of the AccountManager's portal as it publishes it (its public APP_URL), null if unknown.
     */
    public static function householdsUrl(): ?string
    {
        return self::info()['households_url'];
    }

    /**
     * Only asked with HOUSEHOLDS_DRIVER=account-manager and ACCOUNT_MANAGER_URL, so nothing else (and no test) hits the network.
     *
     * @return array{name: ?string, households_url: ?string}
     */
    private static function info(): array
    {
        return once(function (): array {
            $url = config('households.account_manager.url');

            if (config('households.driver') !== 'account-manager' || ! is_string($url) || $url === '') {
                return ['name' => null, 'households_url' => null];
            }

            $cached = Cache::get(self::CACHE_KEY);

            if (is_array($cached) && array_key_exists('name', $cached) && array_key_exists('households_url', $cached)) {
                /** @var array{name: ?string, households_url: ?string} $cached */
                return $cached;
            }

            $info = self::fetch($url);
            $failed = $info['name'] === null && $info['households_url'] === null;

            // A failed request is tried again after 10 minutes, so an outage of the AccountManager does not stick.
            Cache::put(self::CACHE_KEY, $info, $failed ? now()->addMinutes(10) : now()->addDay());

            return $info;
        });
    }

    /**
     * @return array{name: ?string, households_url: ?string}
     */
    private static function fetch(string $url): array
    {
        try {
            $response = Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->acceptJson()->get(rtrim($url, '/').'/api/info');
            $data = $response->successful() ? $response->json() : null;
        } catch (Throwable) {
            $data = null;
        }

        $name = is_array($data) && is_string($data['name'] ?? null) ? trim($data['name']) : '';
        $householdsUrl = is_array($data) && is_string($data['households_url'] ?? null) ? $data['households_url'] : '';

        return [
            'name' => $name !== '' ? mb_substr($name, 0, 50) : null,
            'households_url' => preg_match('#^https?://#i', $householdsUrl) === 1 && filter_var($householdsUrl, FILTER_VALIDATE_URL) !== false
                ? $householdsUrl
                : null,
        ];
    }
}
