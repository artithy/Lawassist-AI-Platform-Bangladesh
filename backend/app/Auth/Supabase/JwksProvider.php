<?php

namespace App\Auth\Supabase;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Supplies Supabase's PUBLIC signing keys (ES256 / P-256) by key id.
 *
 *  - The URL comes only from config, never from a token.
 *  - Only EC P-256 signing keys are kept; everything else in the key set is dropped, and only the public
 *    parameters are cached (a private "d" value, if one were ever present, is discarded).
 *  - Keys are cached for cache_ttl. An unknown key id triggers at most ONE refetch per refetch_interval, so a
 *    flood of random key ids cannot make us hammer Supabase.
 *  - If Supabase is unreachable the last good keys are used for up to stale_ttl; with no keys at all we throw
 *    AuthServiceUnavailable (fail closed). After a failed fetch we back off for failure_backoff seconds.
 *  - Everything is cached in the store named by supabase.jwks.cache_store, never in Laravel's default store.
 *    If that store is undefined ('cache_misconfigured') or fails while being used ('cache_unavailable'), we fail
 *    closed with AuthServiceUnavailable. Only the exception class is logged, and the exception message is generic,
 *    so no cache internals (table names, SQL, paths, hosts) reach an API client.
 */
final class JwksProvider
{
    private const FRESH = 'supabase:jwks:fresh';

    private const LAST_GOOD = 'supabase:jwks:last_good';

    private const REFETCH_MARKER = 'supabase:jwks:refetch';

    private const FAILED_MARKER = 'supabase:jwks:failed';

    private const MAX_BODY_BYTES = 65536;

    private const MAX_KEYS = 20;

    /**
     * @throws InvalidSupabaseToken when no key with that id exists
     * @throws AuthServiceUnavailable when the keys cannot be obtained at all
     */
    public function keyFor(string $kid): Key
    {
        $jwk = $this->keys()[$kid] ?? null;

        if ($jwk === null && $this->mayRefetch()) {
            $jwk = $this->keys(forceRefresh: true)[$kid] ?? null;
        }

        if ($jwk === null) {
            throw new InvalidSupabaseToken('unknown_kid');
        }

        try {
            $key = JWK::parseKey($jwk, 'ES256');
        } catch (Throwable $e) {
            throw new InvalidSupabaseToken('bad_key', $e);
        }

        if (! $key instanceof Key) {
            throw new InvalidSupabaseToken('bad_key');
        }

        return $key;
    }

    /**
     * @return array<string, array<string, string>> public JWKs indexed by kid
     */
    private function keys(bool $forceRefresh = false): array
    {
        if (! $forceRefresh) {
            $fresh = $this->cacheGet(self::FRESH);

            if (is_array($fresh)) {
                return $fresh;
            }
        }

        if ($this->cacheHas(self::FAILED_MARKER)) {
            return $this->staleOrFail(null);
        }

        try {
            $keys = $this->fetch();
        } catch (AuthServiceUnavailable $e) {
            throw $e; // misconfiguration: do not paper over it with old keys
        } catch (Throwable $e) {
            $this->cachePut(self::FAILED_MARKER, 1, (int) config('supabase.jwks.failure_backoff', 15));
            Log::warning('Supabase JWKS fetch failed.', ['error' => $e::class]);

            return $this->staleOrFail($e);
        }

        $this->cachePut(self::FRESH, $keys, (int) config('supabase.jwks.cache_ttl', 600));
        $this->cachePut(self::LAST_GOOD, $keys, (int) config('supabase.jwks.stale_ttl', 3600));
        $this->cachePut(self::REFETCH_MARKER, 1, (int) config('supabase.jwks.refetch_interval', 60)); // just fetched

        return $keys;
    }

    /** @return array<string, array<string, string>> */
    private function staleOrFail(?Throwable $previous): array
    {
        $stale = $this->cacheGet(self::LAST_GOOD);

        if (is_array($stale)) {
            return $stale;
        }

        throw new AuthServiceUnavailable('jwks_unavailable', $previous);
    }

    /** True for the first unknown-kid miss since the last fetch interval elapsed (atomic add). */
    private function mayRefetch(): bool
    {
        return (bool) $this->cacheCall(fn (CacheRepository $cache) => $cache->add(self::REFETCH_MARKER, 1, (int) config('supabase.jwks.refetch_interval', 60)));
    }

    /** The dedicated store. Undefined store => AuthServiceUnavailable('cache_misconfigured'). */
    private function cache(): CacheRepository
    {
        $name = SupabaseConfig::jwksCacheStore();

        try {
            return Cache::store($name);
        } catch (InvalidArgumentException $e) {
            Log::error('Supabase JWKS cache store is not defined.', ['store' => $name]);

            throw new AuthServiceUnavailable('cache_misconfigured', $e);
        } catch (Throwable $e) {
            Log::error('Supabase JWKS cache store could not be opened.', ['store' => $name, 'error' => $e::class]);

            throw new AuthServiceUnavailable('cache_unavailable', $e);
        }
    }

    /**
     * Runs one cache operation. ANY failure of the store at runtime (database down, Redis unreachable, file not
     * writable, ...) fails closed as AuthServiceUnavailable('cache_unavailable'). The original exception is kept as
     * "previous" for server-side debugging only; its message is never part of ours.
     *
     * @param  Closure(CacheRepository): mixed  $operation
     */
    private function cacheCall(Closure $operation): mixed
    {
        $cache = $this->cache();

        try {
            return $operation($cache);
        } catch (Throwable $e) {
            Log::error('Supabase JWKS cache operation failed.', ['error' => $e::class]);

            throw new AuthServiceUnavailable('cache_unavailable', $e);
        }
    }

    private function cacheGet(string $key): mixed
    {
        return $this->cacheCall(fn (CacheRepository $cache) => $cache->get($key));
    }

    private function cacheHas(string $key): bool
    {
        return (bool) $this->cacheCall(fn (CacheRepository $cache) => $cache->has($key));
    }

    private function cachePut(string $key, mixed $value, int $seconds): void
    {
        $this->cacheCall(fn (CacheRepository $cache) => $cache->put($key, $value, $seconds));
    }

    /** @return array<string, array<string, string>> */
    private function fetch(): array
    {
        $response = Http::timeout((int) config('supabase.jwks.timeout', 3))
            ->connectTimeout((int) config('supabase.jwks.connect_timeout', 2))
            ->acceptJson()
            ->withoutRedirecting()
            ->get(SupabaseConfig::jwksUrl());

        if ($response->status() !== 200) {
            throw new RuntimeException('Unexpected JWKS status.');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new RuntimeException('JWKS too large.');
        }

        $data = json_decode($body, true);

        if (! is_array($data) || ! is_array($data['keys'] ?? null)) {
            throw new RuntimeException('JWKS malformed.');
        }

        return $this->usableKeys($data['keys']);
    }

    /**
     * Keeps only ES256 / P-256 verification keys and rebuilds each from public parameters only.
     *
     * @param  array<mixed>  $jwks
     * @return array<string, array<string, string>>
     */
    private function usableKeys(array $jwks): array
    {
        $keys = [];

        foreach ($jwks as $jwk) {
            if (! is_array($jwk)) {
                continue;
            }

            $kid = $jwk['kid'] ?? null;

            if (! is_string($kid) || $kid === '' || strlen($kid) > 128) {
                continue;
            }
            if (($jwk['kty'] ?? null) !== 'EC' || ($jwk['crv'] ?? null) !== 'P-256') {
                continue;
            }
            if (isset($jwk['use']) && $jwk['use'] !== 'sig') {
                continue;
            }
            if (isset($jwk['alg']) && $jwk['alg'] !== 'ES256') {
                continue;
            }
            if (isset($jwk['key_ops']) && ! in_array('verify', (array) $jwk['key_ops'], true)) {
                continue;
            }
            if (! is_string($jwk['x'] ?? null) || ! is_string($jwk['y'] ?? null)) {
                continue;
            }

            $keys[$kid] = [
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => $jwk['x'],
                'y' => $jwk['y'],
                'kid' => $kid,
                'alg' => 'ES256',
                'use' => 'sig',
            ];

            if (count($keys) >= self::MAX_KEYS) {
                break;
            }
        }

        return $keys;
    }
}
