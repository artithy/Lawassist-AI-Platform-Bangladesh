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

    /**
     * True when app_metadata carries an EXPLICIT role that is not exactly one of the three supported names: another
     * word ("suspended", "banned"), a different capitalisation or padding, an empty string, or a value that is not a
     * string at all. A missing "role", a null "role", or no usable app_metadata is NOT explicit: that is simply "user".
     *
     * fromAppMetadata() still answers User for these (the approved default); whoever authenticates a request must
     * ALSO check this and refuse, so that a deliberately set unknown role never becomes ordinary access.
     */
    public static function hasUnsupportedRole(mixed $appMetadata): bool
    {
        if (! is_array($appMetadata) || ! array_key_exists('role', $appMetadata) || $appMetadata['role'] === null) {
            return false;
        }

        $role = $appMetadata['role'];

        return ! is_string($role) || self::tryFrom($role) === null;
    }
}
