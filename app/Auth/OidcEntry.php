<?php

namespace App\Auth;

/**
 * Where the buttons that start an OpenID Connect login lead: AUTH_OIDC_ENTRY_URL (e.g. the
 * AccountManager's /services/<key>/open, which grants access and syncs the groups first), otherwise
 * the app's own redirect. Linking and confirming in the profile always use the redirect, they
 * need the intent in this app's session.
 */
class OidcEntry
{
    public static function url(): string
    {
        $url = config('login.oidc.entry_url');

        return is_string($url) && trim($url) !== '' ? trim($url) : route('oidc.redirect');
    }
}
