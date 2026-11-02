<?php

namespace Tests\Support;

use Firebase\JWT\JWT;
use RuntimeException;

/**
 * Test-only: a locally generated ES256 (P-256) key pair that plays the part of Supabase's signing key.
 * No real Supabase project, key or network request is involved anywhere in the auth tests.
 */
final class SupabaseTestKeys
{
    public const URL = 'https://testproject.supabase.co';

    public const ISSUER = 'https://testproject.supabase.co/auth/v1';

    public const JWKS_URL = 'https://testproject.supabase.co/auth/v1/.well-known/jwks.json';

    public const SUBJECT = '0b8f0f1e-6d5c-4c3a-9a43-2f1d7c9e8a11';

    private function __construct(
        public readonly string $kid,
        private readonly string $privatePem,
        public readonly string $publicPem,
        /** @var array<string, string> */
        public readonly array $jwk,
    ) {}

    public static function generate(string $kid = 'test-key-1'): self
    {
        $resource = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($resource === false || ! openssl_pkey_export($resource, $privatePem)) {
            throw new RuntimeException('OpenSSL could not create an EC key.');
        }

        $details = openssl_pkey_get_details($resource);

        // OpenSSL drops leading zero bytes; a P-256 coordinate must be exactly 32 bytes.
        $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        return new self($kid, $privatePem, $details['key'], [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => self::b64($x),
            'y' => self::b64($y),
            'kid' => $kid,
            'alg' => 'ES256',
            'use' => 'sig',
        ]);
    }

    /** @return array{keys: list<array<string, string>>} */
    public function jwks(): array
    {
        return ['keys' => [$this->jwk]];
    }

    /**
     * Claims a genuine signed-in Supabase user token has. Override or unset what a test needs.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function claims(array $overrides = []): array
    {
        $now = time();

        return array_replace([
            'iss' => self::ISSUER,
            'aud' => 'authenticated',
            'sub' => self::SUBJECT,
            'role' => 'authenticated',
            'email' => 'client@example.com',
            'iat' => $now - 5,
            'exp' => $now + 3600,
            'is_anonymous' => false,
            'app_metadata' => ['provider' => 'google', 'providers' => ['google']],
            'user_metadata' => ['full_name' => 'Test Client'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $extraHeader
     */
    public function sign(array $claims, ?string $kid = null, array $extraHeader = []): string
    {
        return JWT::encode($claims, $this->privatePem, 'ES256', $kid ?? $this->kid, $extraHeader ?: null);
    }

    /** A token signed with the legacy shared secret. */
    public static function signHs256(array $claims, string $secret): string
    {
        return JWT::encode($claims, $secret, 'HS256');
    }

    /** Builds a token by hand (for alg:none and tampering tests). */
    public static function raw(array $header, array $payload, string $signature = ''): string
    {
        return self::b64((string) json_encode($header)).'.'.self::b64((string) json_encode($payload)).'.'.$signature;
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
