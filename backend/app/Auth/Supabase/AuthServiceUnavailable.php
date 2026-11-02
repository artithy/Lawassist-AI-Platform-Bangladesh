<?php

namespace App\Auth\Supabase;

use RuntimeException;
use Throwable;

/**
 * We could not decide (Supabase's public keys are unreachable and none are cached, or the backend is not configured).
 * Authentication fails CLOSED: the request is refused, but as 503 so the client does not sign the user out.
 */
final class AuthServiceUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct('Authentication service unavailable.', 0, $previous);
    }
}
