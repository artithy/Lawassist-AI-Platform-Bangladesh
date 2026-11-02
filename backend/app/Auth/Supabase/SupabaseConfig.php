<?php

namespace App\Auth\Supabase;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Typed, validated access to config/supabase.php. Read at call time (not cached in properties) so tests can change it.
 */
final class SupabaseConfig
{
    /** https://<ref>.supabase.co/auth/v1  : the exact "iss" value a genuine token carries. */
    public static function issuer(): string
    {
        return self::baseUrl().'/auth/v1';
    }

    public static function jwksUrl(): string
    {
        $override = config('supabase.jwks_url');

        if (is_string($override) && trim($override) !== '') {
            return self::requireHttps(trim($override));
        }

        return self::issuer().'/.well-known/jwks.json';
    }

    /**
     * The cache store for the public keys. A missing or empty setting means "file"; it never falls through to
     * Laravel's default store.
     */
    public static function jwksCacheStore(): string
    {
        $store = config('supabase.jwks.cache_store');

        return is_string($store) && trim($store) !== '' ? trim($store) : 'file';
    }

    public static function audience(): string
    {
        $audience = config('supabase.audience');

        return is_string($audience) && $audience !== '' ? $audience : 'authenticated';
    }

    public static function leeway(): int
    {
        return max(0, min(60, (int) config('supabase.leeway', 30)));
    }

    public static function maxTokenBytes(): int
    {
        return max(512, (int) config('supabase.max_token_bytes', 8192));
    }

    /** The legacy HS256 secret, or null when HS256 is switched off / past its accept_until date. */
    public static function legacySecret(): ?string
    {
        $secret = config('supabase.legacy_hs256.secret');

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $until = config('supabase.legacy_hs256.accept_until');

        if ($until !== null && $until !== '') {
            try {
                if (CarbonImmutable::parse((string) $until)->isPast()) {
                    return null;
                }
            } catch (Throwable) {
                return null; // unreadable date: fail closed
            }
        }

        return $secret;
    }

    private static function baseUrl(): string
    {
        $url = config('supabase.url');

        if (! is_string($url) || trim($url) === '') {
            throw new AuthServiceUnavailable('not_configured');
        }

        return rtrim(self::requireHttps(trim($url)), '/');
    }

    private static function requireHttps(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new AuthServiceUnavailable('not_configured');
        }

        return $url;
    }
}
