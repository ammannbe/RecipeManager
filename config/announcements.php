<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Announcements
    |--------------------------------------------------------------------------
    |
    | "none" (default; "local" or empty mean the same): no announcements.
    | "account-manager": announcements are mirrored from the AccountManager
    | (contract: AccountManager docs/ANNOUNCEMENTS-API.md) with the ACCOUNT_MANAGER_*
    | values of config/households.php, synced every 15 minutes, fully once a day
    | and right away on the webhook announcements.changed. Read state, consents and
    | unsubscriptions stay in this app.
    |
    */

    'driver' => env('ANNOUNCEMENTS_DRIVER', 'none'),

    /*
    | Push and e-mail are sent once per announcement, when the app first sees it
    | published. Announcements published longer ago than this (days, at least 1)
    | are only shown, never sent: switching the driver on must not mail old news
    | to everybody. Same variable in Shoppinglist and Chorelist.
    */

    'send_max_age_days' => (int) env('ANNOUNCEMENTS_SEND_MAX_AGE_DAYS', 7),

    /*
    | Recipients per queued job; e-mail jobs start batch_delay seconds apart
    | (shared hosting mailers often limit the rate).
    */

    'batch_size' => (int) env('ANNOUNCEMENTS_BATCH_SIZE', 50),

    'batch_delay' => (int) env('ANNOUNCEMENTS_BATCH_DELAY', 60),

];
