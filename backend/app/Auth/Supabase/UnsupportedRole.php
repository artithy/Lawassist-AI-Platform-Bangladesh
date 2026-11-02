<?php

namespace App\Auth\Supabase;

use RuntimeException;
use Throwable;

/**
 * The token is genuine, but app_metadata.role names a role this application does not know (for example "suspended",
 * "banned", a different capitalisation of "admin", or a value of the wrong type). Someone set that value on purpose, so
 * the caller is refused instead of being quietly treated as an ordinary "user". A generic 403, not a 401: the token
 * itself is fine, and a 401 could make the frontend sign the user out and loop.
 *
 * $reason is a short machine code for logs and tests. It never contains the role value or any other claim.
 */
final class UnsupportedRole extends RuntimeException
{
    public function __construct(public readonly string $reason = 'unsupported_role', ?Throwable $previous = null)
    {
        parent::__construct('Unsupported role.', 0, $previous);
    }
}
