<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Households
    |--------------------------------------------------------------------------
    |
    | "local": households are managed in this app (create, invite by e-mail,
    | roles, leave, delete) and only apply here.
    | "konto": households are mirrored from the AccountManager (webhooks plus
    | a daily sync, members matched by oidc_sub); the local management is
    | read-only, sharing cookbooks with households stays possible.
    |
    */

    'driver' => env('HOUSEHOLDS_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | AccountManager ("Konto")
    |--------------------------------------------------------------------------
    |
    | Contract: AccountManager docs/HOUSEHOLDS-API.md. The secret of the
    | service signs the webhooks (POST /api/konto/webhook) and authenticates
    | the API calls (HTTP Basic with the service key). report_shares: tell the
    | AccountManager which cookbooks are shared with a household (overview).
    |
    */

    'konto' => [
        'url' => env('KONTO_URL'),
        'key' => env('KONTO_SERVICE_KEY'),
        'secret' => env('KONTO_SECRET'),
        'report_shares' => (bool) env('KONTO_REPORT_SHARES', true),
    ],

];
