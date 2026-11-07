<?php

namespace App\Support;

/**
 * Target of the "All rights reserved." link in the mail footer: the page of the connected AccountManager,
 * null (plain text) when this app is not connected to one.
 */
class EasterEgg
{
    public static function url(): ?string
    {
        $base = config('households.account_manager.url');

        return filled($base) ? rtrim((string) $base, '/').'/easter-egg' : null;
    }
}
