<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Households
    |--------------------------------------------------------------------------
    |
    | "local": households are managed in this app (create, invite by e-mail,
    | roles, leave, delete) and only apply here.
    | "account-manager": households are mirrored from the AccountManager (webhooks plus
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
    | service signs the webhooks (POST /api/account-manager/webhook) and authenticates
    | the API calls (HTTP Basic with the service key). report_shares: tell the
    | AccountManager which cookbooks are shared with a household (overview).
    | label: name of the AccountManager in the household texts; empty: the name it
    | publishes at /api/info (App\Households\AccountManagerLabel), otherwise "Account".
    |
    */

    'account_manager' => [
        'url' => env('ACCOUNT_MANAGER_URL'),
        'key' => env('ACCOUNT_MANAGER_SERVICE_KEY'),
        'secret' => env('ACCOUNT_MANAGER_SECRET'),
        'report_shares' => (bool) env('ACCOUNT_MANAGER_REPORT_SHARES', true),
        'label' => env('ACCOUNT_MANAGER_LABEL'),
    ],

];
