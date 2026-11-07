<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Households
    |--------------------------------------------------------------------------
    |
    | "local": households are managed in this app (create, invite by e-mail,
    | roles, leave, delete) and only apply here.
    | "konto": households come from the AccountManager (not implemented yet);
    | the local management is read-only, existing households and their
    | cookbook shares keep working.
    |
    */

    'driver' => env('HOUSEHOLDS_DRIVER', 'local'),

];
