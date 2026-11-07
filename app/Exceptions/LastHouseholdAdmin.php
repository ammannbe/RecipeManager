<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Removing, demoting or letting go the last admin of a household would leave it unmanageable.
 */
class LastHouseholdAdmin extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('A household needs at least one admin. Make another member admin first.'));
    }
}
