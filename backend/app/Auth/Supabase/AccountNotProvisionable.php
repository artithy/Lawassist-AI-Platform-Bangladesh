<?php

namespace App\Auth\Supabase;

use RuntimeException;
use Throwable;

/**
 * The token is genuine, but no Laravel account can be created from it (for example it carries no usable email).
 * The caller is authenticated yet not allowed to proceed, so this is a generic 403, not a 401: a 401 could make the
 * frontend sign the user out and loop.
 *
 * $reason is a short machine code for logs and tests. It never contains an email or any claim value.
 */
final class AccountNotProvisionable extends RuntimeException
{
    public function __construct(public readonly string $reason = 'no_usable_email', ?Throwable $previous = null)
    {
        parent::__construct('Account cannot be set up.', 0, $previous);
    }
}
