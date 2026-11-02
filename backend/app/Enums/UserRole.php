<?php

namespace App\Enums;

/**
 * The application roles. A role is NEVER stored in Laravel and never read from request input: it comes only from
 * the verified Supabase token's app_metadata.role (a field only Supabase admins / the service role can change).
 */
enum UserRole: string
{
    case User = 'user';
    case Lawyer = 'lawyer';
    case Admin = 'admin';

    /**
     * Reads app_metadata.role. Anything missing, not a string, or not exactly one of the three role names
     * (including different capitalisation) becomes the least-privileged role, User.
     */
    public static function fromAppMetadata(mixed $appMetadata): self
    {
        if (! is_array($appMetadata)) {
            return self::User;
        }

        $role = $appMetadata['role'] ?? null;

        return is_string($role) ? (self::tryFrom($role) ?? self::User) : self::User;
    }
}
