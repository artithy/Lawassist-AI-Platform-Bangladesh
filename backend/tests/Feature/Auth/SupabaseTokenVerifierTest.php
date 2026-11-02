<?php

namespace Tests\Feature\Auth;

use App\Auth\Supabase\AuthServiceUnavailable;
use App\Auth\Supabase\InvalidSupabaseToken;
use App\Auth\Supabase\SupabaseTokenVerifier;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Http;
use Tests\Support\ConfiguresSupabase;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

class SupabaseTokenVerifierTest extends TestCase
{
    use ConfiguresSupabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();
    }

    private function verifyToken(string $token)
    {
        return app(SupabaseTokenVerifier::class)->verify($token);
    }

    private function assertTokenRejected(string $token, string $reason): void
    {
        try {
            $this->verifyToken($token);
            $this->fail("Token was accepted but should have been rejected as [{$reason}].");
        } catch (InvalidSupabaseToken $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    // ---- accepted -------------------------------------------------------------------------------------------

    public function test_a_valid_es256_token_is_accepted(): void
    {
        $verified = $this->verifyToken($this->keys->sign(SupabaseTestKeys::claims()));

        $this->assertSame(SupabaseTestKeys::SUBJECT, $verified->subject);
        $this->assertSame('client@example.com', $verified->email);
        $this->assertSame('Test Client', $verified->displayName);
        $this->assertSame(UserRole::User, $verified->role);
        $this->assertSame('ES256', $verified->algorithm);
        $this->assertSame('test-key-1', $verified->keyId);
    }

    public function test_subject_is_lowercased(): void
    {
        $token = $this->keys->sign(SupabaseTestKeys::claims(['sub' => strtoupper(SupabaseTestKeys::SUBJECT)]));

        $this->assertSame(SupabaseTestKeys::SUBJECT, $this->verifyToken($token)->subject);
    }

    public function test_audience_may_be_a_list_that_contains_authenticated(): void
    {
        $token = $this->keys->sign(SupabaseTestKeys::claims(['aud' => ['other', 'authenticated']]));

        $this->assertSame(SupabaseTestKeys::SUBJECT, $this->verifyToken($token)->subject);
    }

    public function test_small_clock_differences_are_tolerated(): void
    {
        $expiredJustNow = $this->keys->sign(SupabaseTestKeys::claims(['exp' => time() - 10]));
        $startsSoon = $this->keys->sign(SupabaseTestKeys::claims(['nbf' => time() + 10]));

        $this->assertSame(SupabaseTestKeys::SUBJECT, $this->verifyToken($expiredJustNow)->subject);
        $this->assertSame(SupabaseTestKeys::SUBJECT, $this->verifyToken($startsSoon)->subject);
    }

    // ---- time -----------------------------------------------------------------------------------------------

    public function test_expired_token_is_rejected(): void
    {
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['exp' => time() - 120])), 'expired');
    }

    public function test_token_that_is_not_valid_yet_is_rejected(): void
    {
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['nbf' => time() + 120])), 'not_yet_valid');
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['iat' => time() + 120])), 'not_yet_valid');
    }

    public function test_token_without_exp_is_rejected(): void
    {
        $claims = SupabaseTestKeys::claims();
        unset($claims['exp']);

        $this->assertTokenRejected($this->keys->sign($claims), 'missing_exp');
    }

    // ---- claims ---------------------------------------------------------------------------------------------

    public function test_wrong_or_missing_issuer_is_rejected(): void
    {
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['iss' => 'https://other.supabase.co/auth/v1'])), 'bad_issuer');
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['iss' => SupabaseTestKeys::ISSUER.'/'])), 'bad_issuer');

        $claims = SupabaseTestKeys::claims();
        unset($claims['iss']);
        $this->assertTokenRejected($this->keys->sign($claims), 'bad_issuer');
    }

    public function test_wrong_or_missing_audience_is_rejected(): void
    {
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['aud' => 'something-else'])), 'bad_audience');
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['aud' => ['a', 'b']])), 'bad_audience');

        $claims = SupabaseTestKeys::claims();
        unset($claims['aud']);
        $this->assertTokenRejected($this->keys->sign($claims), 'bad_audience');
    }

    public function test_anon_key_and_missing_role_claims_are_rejected(): void
    {
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['role' => 'anon'])), 'bad_role_claim');
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['role' => 'service_role'])), 'bad_role_claim');
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['role' => 'admin'])), 'bad_role_claim');

        $claims = SupabaseTestKeys::claims();
        unset($claims['role']);
        $this->assertTokenRejected($this->keys->sign($claims), 'bad_role_claim');
    }

    public function test_anonymous_sign_in_tokens_are_rejected(): void
    {
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['is_anonymous' => true])), 'anonymous');
    }

    public function test_subject_must_be_a_real_uuid(): void
    {
        foreach (['not-a-uuid', '', '00000000-0000-0000-0000-000000000000', 12345, '0b8f0f1e-6d5c-4c3a-9a43-2f1d7c9e8a1', "0b8f0f1e-6d5c-4c3a-9a43-2f1d7c9e8a11'; --"] as $subject) {
            $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(['sub' => $subject])), 'bad_subject');
        }

        $claims = SupabaseTestKeys::claims();
        unset($claims['sub']);
        $this->assertTokenRejected($this->keys->sign($claims), 'bad_subject');
    }

    // ---- signature / algorithm / header ---------------------------------------------------------------------

    public function test_tampered_payload_is_rejected(): void
    {
        [$header, , $signature] = explode('.', $this->keys->sign(SupabaseTestKeys::claims()));
        $evilPayload = SupabaseTestKeys::b64((string) json_encode(SupabaseTestKeys::claims(['app_metadata' => ['role' => 'admin']])));

        $this->assertTokenRejected("{$header}.{$evilPayload}.{$signature}", 'bad_signature');
    }

    public function test_token_signed_by_a_different_key_with_the_same_kid_is_rejected(): void
    {
        $attacker = SupabaseTestKeys::generate('test-key-1');

        $this->assertTokenRejected($attacker->sign(SupabaseTestKeys::claims()), 'bad_signature');
    }

    public function test_unknown_kid_is_rejected(): void
    {
        $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(), 'no-such-key'), 'unknown_kid');
    }

    public function test_es256_token_without_kid_is_rejected(): void
    {
        $token = SupabaseTestKeys::raw(['alg' => 'ES256', 'typ' => 'JWT'], SupabaseTestKeys::claims(), 'c2ln');

        $this->assertTokenRejected($token, 'missing_kid');
    }

    public function test_alg_none_is_rejected(): void
    {
        $this->assertTokenRejected(SupabaseTestKeys::raw(['alg' => 'none', 'typ' => 'JWT'], SupabaseTestKeys::claims(), 'c2ln'), 'alg_not_allowed');
        $this->assertTokenRejected(SupabaseTestKeys::raw(['alg' => 'none', 'typ' => 'JWT'], SupabaseTestKeys::claims()), 'malformed');
        $this->assertTokenRejected(SupabaseTestKeys::raw(['alg' => 'None', 'typ' => 'JWT'], SupabaseTestKeys::claims(), 'c2ln'), 'alg_not_allowed');
    }

    public function test_other_algorithms_are_rejected(): void
    {
        foreach (['RS256', 'ES384', 'HS384', 'HS512', 'es256', ''] as $alg) {
            $token = SupabaseTestKeys::raw(['alg' => $alg, 'kid' => 'test-key-1', 'typ' => 'JWT'], SupabaseTestKeys::claims(), 'c2ln');
            $this->assertTokenRejected($token, 'alg_not_allowed');
        }
    }

    public function test_tokens_that_name_their_own_keys_are_rejected(): void
    {
        foreach (['jku' => 'https://evil.example/jwks.json', 'jwk' => ['kty' => 'EC'], 'x5u' => 'https://evil.example/c.pem'] as $name => $value) {
            $this->assertTokenRejected($this->keys->sign(SupabaseTestKeys::claims(), null, [$name => $value]), 'unsupported_header');
        }
    }

    public function test_malformed_and_oversized_tokens_are_rejected(): void
    {
        $this->assertTokenRejected('', 'malformed');
        $this->assertTokenRejected('abc', 'malformed');
        $this->assertTokenRejected('abc.def', 'malformed');
        $this->assertTokenRejected('a.b.c.d', 'malformed');
        $this->assertTokenRejected('not base64!.x.y', 'malformed');
        $this->assertTokenRejected(SupabaseTestKeys::b64('not json').'.e30.c2ln', 'malformed');
        $this->assertTokenRejected(str_repeat('a', 9000).'.b.c', 'too_large');
    }

    // ---- legacy HS256 transition ----------------------------------------------------------------------------

    private function enableLegacyHs256(?string $until = null): string
    {
        $secret = bin2hex(random_bytes(32));
        config(['supabase.legacy_hs256.secret' => $secret, 'supabase.legacy_hs256.accept_until' => $until]);

        return $secret;
    }

    public function test_hs256_is_rejected_when_no_legacy_secret_is_configured(): void
    {
        $token = SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(), bin2hex(random_bytes(32)));

        $this->assertTokenRejected($token, 'alg_not_allowed');
    }

    public function test_hs256_signed_with_the_legacy_secret_is_accepted_during_the_transition(): void
    {
        $secret = $this->enableLegacyHs256();

        $verified = $this->verifyToken(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(), $secret));

        $this->assertSame(SupabaseTestKeys::SUBJECT, $verified->subject);
        $this->assertSame('HS256', $verified->algorithm);
        $this->assertNull($verified->keyId);
    }

    public function test_hs256_with_the_wrong_secret_is_rejected(): void
    {
        $this->enableLegacyHs256();

        $this->assertTokenRejected(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(), bin2hex(random_bytes(32))), 'bad_signature');
    }

    public function test_hs256_signed_with_the_public_key_is_rejected_whether_or_not_legacy_is_on(): void
    {
        // The classic algorithm-confusion attack: HMAC-sign using the PUBLIC key as the secret.
        $forged = SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(['app_metadata' => ['role' => 'admin']]), $this->keys->publicPem);

        $this->assertTokenRejected($forged, 'alg_not_allowed');

        $this->enableLegacyHs256();
        $this->assertTokenRejected($forged, 'bad_signature');
    }

    public function test_hs256_stops_working_after_the_accept_until_date(): void
    {
        $secret = $this->enableLegacyHs256(gmdate('c', time() - 60));
        $this->assertTokenRejected(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(), $secret), 'alg_not_allowed');

        config(['supabase.legacy_hs256.accept_until' => gmdate('c', time() + 3600)]);
        $this->assertSame(SupabaseTestKeys::SUBJECT, $this->verifyToken(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(), $secret))->subject);
    }

    public function test_an_unreadable_accept_until_value_turns_hs256_off(): void
    {
        $secret = $this->enableLegacyHs256('not-a-date');

        $this->assertTokenRejected(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(), $secret), 'alg_not_allowed');
    }

    public function test_hs256_tokens_still_go_through_the_claim_checks(): void
    {
        $secret = $this->enableLegacyHs256();

        $this->assertTokenRejected(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(['exp' => time() - 300]), $secret), 'expired');
        $this->assertTokenRejected(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(['iss' => 'https://x.supabase.co/auth/v1']), $secret), 'bad_issuer');
        $this->assertTokenRejected(SupabaseTestKeys::signHs256(SupabaseTestKeys::claims(['role' => 'anon']), $secret), 'bad_role_claim');
    }

    public function test_es256_still_works_while_legacy_is_on(): void
    {
        $this->enableLegacyHs256();

        $this->assertSame('ES256', $this->verifyToken($this->keys->sign(SupabaseTestKeys::claims()))->algorithm);
    }

    // ---- configuration / availability -----------------------------------------------------------------------

    public function test_unconfigured_backend_fails_closed_with_service_unavailable(): void
    {
        config(['supabase.url' => null]);

        $this->expectException(AuthServiceUnavailable::class);
        $this->verifyToken($this->keys->sign(SupabaseTestKeys::claims()));
    }

    public function test_non_https_project_url_is_treated_as_unconfigured(): void
    {
        config(['supabase.url' => 'http://testproject.supabase.co']);

        $this->expectException(AuthServiceUnavailable::class);
        $this->verifyToken($this->keys->sign(SupabaseTestKeys::claims()));
    }

    public function test_unreachable_supabase_with_no_cached_keys_fails_closed(): void
    {
        $this->jwksUnreachable = true;

        $this->expectException(AuthServiceUnavailable::class);
        $this->verifyToken($this->keys->sign(SupabaseTestKeys::claims()));
    }

    public function test_verification_makes_no_real_network_request(): void
    {
        $this->verifyToken($this->keys->sign(SupabaseTestKeys::claims()));

        Http::assertSent(fn ($request) => $request->url() === SupabaseTestKeys::JWKS_URL);
        Http::assertSentCount(1);
    }
}
