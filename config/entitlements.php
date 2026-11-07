<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entitlements
    |--------------------------------------------------------------------------
    |
    | "local": every user has the local features below and access to the app.
    | "account-manager": access status and features of users with a linked
    | sign-in (oidc_sub) come from the AccountManager (contract: AccountManager
    | docs/ENTITLEMENTS-API.md): the last snapshot per user is stored in
    | user_entitlements (webhook entitlements.changed, sync on the OIDC login
    | and daily entitlements:sync). A suspended or missing access blocks the
    | app for that user. Users without a linked sign-in (e.g. emergency admins)
    | keep the local features. Uses the ACCOUNT_MANAGER_* values of
    | config/households.php.
    |
    */

    'driver' => env('ENTITLEMENTS_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Admin rights
    |--------------------------------------------------------------------------
    |
    | "local": users.admin. "account-manager": linked users are admins when
    | the AccountManager says so ("Admin im Dienst"), users without a linked
    | sign-in keep users.admin (emergency admins stay admins).
    |
    */

    'admin_source' => env('AUTH_ADMIN_SOURCE', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Local features
    |--------------------------------------------------------------------------
    |
    | Feature keys every user has with the local driver, and users without a
    | linked sign-in with the account-manager driver (comma separated). The AI
    | features of this app are switched per user (users.ai_enabled) and need
    | no key yet.
    |
    */

    'local' => [
        'features' => array_values(array_filter(array_map('trim', explode(',', (string) env('ENTITLEMENTS_LOCAL_FEATURES', ''))))),
    ],

];
