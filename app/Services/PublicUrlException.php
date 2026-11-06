<?php

namespace App\Services;

use RuntimeException;

/**
 * A public address that may not be read (internal host) or could not be loaded.
 */
class PublicUrlException extends RuntimeException
{
    public const NOT_PUBLIC = 'Only public web addresses can be read.';

    public const FAILED = 'The address could not be loaded.';

    public const TOO_LARGE = 'The file is too large.';

    public function isNotPublic(): bool
    {
        return $this->getMessage() === self::NOT_PUBLIC;
    }
}
