<?php

namespace App\Auth;

use RuntimeException;

class OidcLoginException extends RuntimeException
{
    /**
     * The message is a translation key (English text), shown to the user.
     */
    public static function because(string $message): self
    {
        return new self($message);
    }
}
