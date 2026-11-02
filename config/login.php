<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local login
    |--------------------------------------------------------------------------
    |
    | E-mail and password login of the app itself. When disabled, only admins
    | may still sign in with a password (emergency access, e.g. while the
    | OpenID Connect provider is down). Registration and password reset are
    | only offered while the local login is enabled.
    |
    */

    'local' => [
        'enabled' => (bool) env('AUTH_LOCAL_ENABLED', true),
        'registration' => (bool) env('AUTH_LOCAL_REGISTRATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenID Connect login
    |--------------------------------------------------------------------------
    |
    | Optional "Sign in with …" through any OpenID Connect provider (e.g.
    | Pocket ID). The provider connection itself is configured in
    | config/services.php ("oidc").
    |
    */

    'oidc' => [
        'enabled' => (bool) env('AUTH_OIDC_ENABLED', false),
        // Name on the buttons; empty: the APP_NAME of a Pocket ID provider, otherwise "SSO" (App\Auth\OidcLabel).
        'label' => env('AUTH_OIDC_LABEL') ?: null,

        // Optional: where users create an account at the provider (e.g. the AccountManager's /register).
        'signup_url' => env('AUTH_OIDC_SIGNUP_URL') ?: null,

        // Create a new account on the first OIDC login of an unknown user.
        'registration' => (bool) env('AUTH_OIDC_REGISTRATION', true),

        // Only users with this value in the "groups" claim may sign in.
        'required_group' => env('AUTH_OIDC_REQUIRED_GROUP') ?: null,
    ],

];
