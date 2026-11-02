<?php

namespace App\Auth\Supabase;

use App\Enums\UserRole;

/**
 * The claims of a token that has ALREADY passed every check in SupabaseTokenVerifier. Nothing else in the app
 * should build one from unverified data.
 *
 *  - $subject   : the Supabase user id (lowercase UUID). The only identity key.
 *  - $role      : from app_metadata.role only (defaults to User). user_metadata and the top-level "role" claim
 *                 ("authenticated") are never used for authorization.
 *  - $email     : the token's email (lower-cased) or null. Display/contact copy only, never an identity key.
 *  - $displayName: from user_metadata, which the user can edit themselves: text for display only, never for
 *                 authorization decisions.
 */
final readonly class VerifiedToken
{
    public function __construct(
        public string $subject,
        public UserRole $role,
        public ?string $email,
        public ?string $displayName,
        public int $expiresAt,
        public string $algorithm,
        public ?string $keyId,
    ) {}

    /**
     * @param  array<string, mixed>  $claims  claims that were already validated by the verifier
     */
    public static function fromClaims(array $claims, string $algorithm, ?string $keyId): self
    {
        return new self(
            subject: strtolower((string) $claims['sub']),
            role: UserRole::fromAppMetadata($claims['app_metadata'] ?? null),
            email: self::cleanEmail($claims['email'] ?? null),
            displayName: self::cleanName($claims['user_metadata'] ?? null),
            expiresAt: (int) $claims['exp'],
            algorithm: $algorithm,
            keyId: $keyId,
        );
    }

    private static function cleanEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));

        return strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private static function cleanName(mixed $userMetadata): ?string
    {
        if (! is_array($userMetadata)) {
            return null;
        }

        $name = $userMetadata['full_name'] ?? $userMetadata['name'] ?? null;

        if (! is_string($name)) {
            return null;
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name));
        $name = mb_substr($name, 0, 255);

        return $name === '' ? null : $name;
    }
}
