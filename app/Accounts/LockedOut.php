<?php

namespace App\Accounts;

use RuntimeException;

/** Too many failed attempts to log in with one email from one address. */
final class LockedOut extends RuntimeException
{
    public function __construct(public readonly int $seconds)
    {
        parent::__construct('Too many attempts. Try again in a minute.');
    }
}
