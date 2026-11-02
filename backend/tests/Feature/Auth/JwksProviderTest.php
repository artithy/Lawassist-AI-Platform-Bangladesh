<?php

namespace Tests\Feature\Auth;

use App\Auth\Supabase\AuthServiceUnavailable;
use App\Auth\Supabase\InvalidSupabaseToken;
use App\Auth\Supabase\JwksProvider;
use App\Auth\Supabase\SupabaseTokenVerifier;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\ConfiguresSupabase;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

class JwksProviderTest extends TestCase
{
    use ConfiguresSupabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();
    }

    private function jwksProvider(): JwksProvider
    {
        return app(JwksProvider::class);
    }

    /** The store the provider is configured to use (the in-memory "array" store unless a test changes it). */
    private function jwksCache(): Repository
    {
        return Cache::store(config('supabase.jwks.cache_store'));
    }

    /** Every cache entry the provider can create. */
    private const CACHE_KEYS = [
        'supabase:jwks:fresh',
        'supabase:jwks:last_good',
        'supabase:jwks:refetch',
        'supabase:jwks:failed',
    ];

    public function test_returns_the_key_for_a_known_kid(): void
    {
        $this->assertInstanceOf(Key::class, $this->jwksProvider()->keyFor('test-key-1'));
    }

    public function test_keys_are_cached_between_calls(): void
    {
        $this->jwksProvider()->keyFor('test-key-1');
        $this->jwksProvider()->keyFor('test-key-1');
        $this->jwksProvider()->keyFor('test-key-1');

        Http::assertSentCount(1);
    }

    public function test_a_flood_of_unknown_key_ids_causes_at_most_one_refetch_per_interval(): void
    {
        foreach (range(1, 20) as $i) {
            try {
                $this->jwksProvider()->keyFor("random-{$i}");
            } catch (InvalidSupabaseToken $e) {
                $this->assertSame('unknown_kid', $e->reason);
            }
        }

        Http::assertSentCount(1); // the first fetch also counts as the refetch for this interval

        $this->travel(61)->seconds();

        try {
            $this->jwksProvider()->keyFor('random-again');
        } catch (InvalidSupabaseToken) {
        }
        try {
            $this->jwksProvider()->keyFor('random-again-2');
        } catch (InvalidSupabaseToken) {
        }

        Http::assertSentCount(2);
    }

    public function test_a_rotated_key_is_picked_up_after_the_refetch_interval(): void
    {
        $this->jwksProvider()->keyFor('test-key-1');

        $newKeys = SupabaseTestKeys::generate('test-key-2');
        $this->jwksBody = ['keys' => [$this->keys->jwk, $newKeys->jwk]];

        $this->travel(61)->seconds();

        $this->assertInstanceOf(Key::class, $this->jwksProvider()->keyFor('test-key-2'));
    }

    public function test_last_good_keys_are_used_when_supabase_is_down(): void
    {
        $this->jwksProvider()->keyFor('test-key-1');

        $this->travel(601)->seconds(); // fresh copy expired, stale copy still allowed
        $this->jwksStatus = 500;

        $this->assertInstanceOf(Key::class, $this->jwksProvider()->keyFor('test-key-1'));
    }

    public function test_after_a_failed_fetch_supabase_is_not_hammered(): void
    {
        $this->jwksProvider()->keyFor('test-key-1');
        $this->travel(601)->seconds();
        $this->jwksStatus = 500;

        $this->jwksProvider()->keyFor('test-key-1'); // tries once, falls back to the last good keys
        $this->jwksProvider()->keyFor('test-key-1'); // within the back-off: no new request
        $this->jwksProvider()->keyFor('test-key-1');

        Http::assertSentCount(2);

        $this->travel(16)->seconds(); // back-off over: it may try again
        $this->jwksProvider()->keyFor('test-key-1');

        Http::assertSentCount(3);
    }

    public function test_stale_keys_stop_being_used_after_the_stale_window(): void
    {
        $this->jwksProvider()->keyFor('test-key-1');

        $this->travel(3601)->seconds();
        $this->jwksUnreachable = true;

        $this->expectException(AuthServiceUnavailable::class);
        $this->jwksProvider()->keyFor('test-key-1');
    }

    public function test_no_keys_at_all_fails_closed(): void
    {
        $this->jwksStatus = 503;

        $this->expectException(AuthServiceUnavailable::class);
        $this->jwksProvider()->keyFor('test-key-1');
    }

    public function test_malformed_responses_fail_closed(): void
    {
        foreach ([['nope' => true], ['keys' => 'x']] as $body) {
            $this->jwksCache()->flush();
            $this->jwksBody = $body;

            try {
                $this->jwksProvider()->keyFor('test-key-1');
                $this->fail('Expected AuthServiceUnavailable.');
            } catch (AuthServiceUnavailable $e) {
                $this->assertSame('jwks_unavailable', $e->reason);
            }
        }
    }

    public function test_only_es256_p256_signing_keys_are_kept(): void
    {
        $rsa = ['kty' => 'RSA', 'kid' => 'rsa-key', 'n' => 'AQAB', 'e' => 'AQAB', 'alg' => 'RS256', 'use' => 'sig'];
        $p384 = array_replace($this->keys->jwk, ['kid' => 'p384', 'crv' => 'P-384']);
        $encryptionOnly = array_replace($this->keys->jwk, ['kid' => 'enc', 'use' => 'enc']);
        $wrongAlg = array_replace($this->keys->jwk, ['kid' => 'wrong-alg', 'alg' => 'ES384']);
        $noKid = array_diff_key($this->keys->jwk, ['kid' => true]);
        $this->jwksBody = ['keys' => [$rsa, $p384, $encryptionOnly, $wrongAlg, $noKid, 'junk', $this->keys->jwk]];

        $this->assertInstanceOf(Key::class, $this->jwksProvider()->keyFor('test-key-1'));

        foreach (['rsa-key', 'p384', 'enc', 'wrong-alg'] as $kid) {
            $this->travel(61)->seconds();
            try {
                $this->jwksProvider()->keyFor($kid);
                $this->fail("{$kid} should not be usable.");
            } catch (InvalidSupabaseToken $e) {
                $this->assertSame('unknown_kid', $e->reason);
            }
        }
    }

    public function test_private_key_material_is_never_cached(): void
    {
        $this->jwksBody = ['keys' => [array_replace($this->keys->jwk, ['d' => 'PRIVATE-VALUE'])]];

        $this->jwksProvider()->keyFor('test-key-1');

        $this->assertStringNotContainsString('PRIVATE-VALUE', json_encode($this->jwksCache()->get('supabase:jwks:fresh')));
        $this->assertStringNotContainsString('PRIVATE-VALUE', json_encode($this->jwksCache()->get('supabase:jwks:last_good')));
    }

    public function test_keys_are_only_requested_from_the_configured_https_url(): void
    {
        $this->jwksProvider()->keyFor('test-key-1');

        Http::assertSent(fn ($request) => $request->url() === SupabaseTestKeys::JWKS_URL && $request->method() === 'GET');
    }

    public function test_a_non_https_jwks_override_is_refused_and_nothing_is_requested(): void
    {
        config(['supabase.jwks_url' => 'http://insecure.example/jwks.json']);

        try {
            $this->jwksProvider()->keyFor('test-key-1');
            $this->fail('Expected AuthServiceUnavailable.');
        } catch (AuthServiceUnavailable $e) {
            $this->assertSame('not_configured', $e->reason);
        }

        Http::assertNothingSent();
    }

    // ---- dedicated cache store ------------------------------------------------------------------------------

    /** Makes the default cache store and the JWKS store two DIFFERENT stores. */
    private function useSeparateStores(): void
    {
        config([
            'cache.default' => 'array',
            'cache.stores.jwks_test' => ['driver' => 'array', 'serialize' => true],
            'supabase.jwks.cache_store' => 'jwks_test',
        ]);
    }

    public function test_keys_and_markers_are_cached_in_the_dedicated_store_not_the_default_store(): void
    {
        $this->useSeparateStores();

        $this->jwksProvider()->keyFor('test-key-1');

        foreach (['supabase:jwks:fresh', 'supabase:jwks:last_good', 'supabase:jwks:refetch'] as $key) {
            $this->assertTrue(Cache::store('jwks_test')->has($key), "{$key} should be in the dedicated store");
        }

        foreach (self::CACHE_KEYS as $key) {
            $this->assertFalse(Cache::store('array')->has($key), "{$key} must not be in the default store");
            $this->assertFalse(Cache::has($key), "{$key} must not be in the default store");
        }
    }

    public function test_the_failure_marker_is_also_kept_in_the_dedicated_store_only(): void
    {
        $this->useSeparateStores();
        $this->jwksStatus = 500;

        try {
            $this->jwksProvider()->keyFor('test-key-1');
            $this->fail('Expected AuthServiceUnavailable.');
        } catch (AuthServiceUnavailable $e) {
            $this->assertSame('jwks_unavailable', $e->reason);
        }

        $this->assertTrue(Cache::store('jwks_test')->has('supabase:jwks:failed'));

        foreach (self::CACHE_KEYS as $key) {
            $this->assertFalse(Cache::store('array')->has($key));
        }
    }

    public function test_a_missing_store_setting_means_file_and_never_the_default_store(): void
    {
        config([
            'cache.default' => 'array',
            'cache.stores.file' => ['driver' => 'array', 'serialize' => true], // stand-in so no real files are written
            'supabase.jwks.cache_store' => null,
        ]);

        $this->jwksProvider()->keyFor('test-key-1');

        $this->assertTrue(Cache::store('file')->has('supabase:jwks:fresh'));

        foreach (self::CACHE_KEYS as $key) {
            $this->assertFalse(Cache::store('array')->has($key));
        }
    }

    public function test_an_undefined_store_fails_closed_without_contacting_supabase(): void
    {
        config(['supabase.jwks.cache_store' => 'no_such_store']);

        try {
            $this->jwksProvider()->keyFor('test-key-1');
            $this->fail('Expected AuthServiceUnavailable.');
        } catch (AuthServiceUnavailable $e) {
            $this->assertSame('cache_misconfigured', $e->reason);
        }

        Http::assertNothingSent();
    }

    public function test_a_cache_store_that_fails_at_runtime_fails_closed_without_leaking_details(): void
    {
        // A database store pointing at a table that does not exist: the first read raises a QueryException.
        config([
            'cache.stores.broken_jwks' => ['driver' => 'database', 'table' => 'no_such_table_secret_name'],
            'supabase.jwks.cache_store' => 'broken_jwks',
        ]);

        try {
            $this->jwksProvider()->keyFor('test-key-1');
            $this->fail('Expected AuthServiceUnavailable.');
        } catch (AuthServiceUnavailable $e) {
            $this->assertSame('cache_unavailable', $e->reason);
            $this->assertSame('Authentication service unavailable.', $e->getMessage());
            $this->assertStringNotContainsString('no_such_table_secret_name', $e->getMessage());
            $this->assertNotNull($e->getPrevious()); // kept for server-side debugging only
        }

        Http::assertNothingSent(); // it failed closed before asking Supabase for anything
    }

    public function test_a_failing_cache_store_also_fails_closed_through_the_verifier(): void
    {
        config([
            'cache.stores.broken_jwks' => ['driver' => 'database', 'table' => 'no_such_table'],
            'supabase.jwks.cache_store' => 'broken_jwks',
        ]);

        $token = $this->keys->sign(SupabaseTestKeys::claims());

        try {
            app(SupabaseTokenVerifier::class)->verify($token);
            $this->fail('Expected AuthServiceUnavailable.');
        } catch (AuthServiceUnavailable $e) {
            $this->assertSame('cache_unavailable', $e->reason);
        }
    }
}
