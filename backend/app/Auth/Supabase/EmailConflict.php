<?php

namespace App\Auth\Supabase;

use RuntimeException;
use Throwable;

/**
 * A verified Supabase user wants a Laravel account, but that email already belongs to a DIFFERENT Laravel user.
 * Accounts are never linked by email, so the request is refused with a generic 409.
 *
 * $reason is a short machine code for logs and tests. It never contains the email or any claim value.
 */
final class EmailConflict extends RuntimeException
{
    public readonly string $reason;

    public function __construct(?Throwable $previous = null)
    {
        $this->reason = 'email_conflict';

        parent::__construct('Email already in use.', 0, $previous);
    }
}
