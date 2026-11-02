<?php

namespace App\Auth\Supabase;

use RuntimeException;
use Throwable;

/**
 * The token is not acceptable (bad signature, expired, wrong issuer, ...). The client only ever sees a generic 401;
 * $reason is a short machine code for logs and tests. It never contains the token or any claim value.
 */
final class InvalidSupabaseToken extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct('Invalid access token.', 0, $previous);
    }
}
