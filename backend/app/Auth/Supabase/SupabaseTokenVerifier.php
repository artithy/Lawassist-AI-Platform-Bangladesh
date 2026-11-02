<?php

namespace App\Auth\Supabase;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Throwable;

/**
 * Verifies a Supabase access token. It never returns data from a token it has not fully verified.
 *
 * Accepted algorithms (fixed in code, not configurable):
 *   ES256  primary. Verified with the public key whose "kid" matches, taken from Supabase's JWKS.
 *   HS256  transition only. Verified with the legacy shared secret, and only while that secret is configured and
 *          its accept_until date (if any) has not passed. The HS256 key is only ever that secret, never a public key.
 * Everything else (including "none") is rejected.
 *
 * Then: signature, exp (required), nbf/iat, issuer (exact), audience, top-level role = "authenticated", not an
 * anonymous user, and "sub" must be a UUID.
 *
 * @throws InvalidSupabaseToken for any bad token
 * @throws AuthServiceUnavailable when the backend cannot decide (not configured / keys unreachable)
 */
final class SupabaseTokenVerifier
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const NIL_UUID = '00000000-0000-0000-0000-000000000000';

    /** Headers that let a token point at keys of its own choosing. No genuine Supabase token has them. */
    private const FORBIDDEN_HEADERS = ['jku', 'jwk', 'x5u', 'x5c', 'crit'];

    public function __construct(private readonly JwksProvider $jwks) {}

    public function verify(string $token): VerifiedToken
    {
        $issuer = SupabaseConfig::issuer(); // also proves the backend is configured

        $header = $this->parseHeader($token);
        [$key, $kid] = $this->selectKey($header);

        $claims = $this->decode($token, $key);
        $this->assertClaims($claims, $issuer);

        return VerifiedToken::fromClaims($claims, $header['alg'], $kid);
    }

    /**
     * Reads ONLY the token header, to decide which key to use. Nothing in it is trusted beyond that.
     *
     * @return array<string, mixed>
     */
    private function parseHeader(string $token): array
    {
        if (strlen($token) > SupabaseConfig::maxTokenBytes()) {
            throw new InvalidSupabaseToken('too_large');
        }

        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new InvalidSupabaseToken('malformed');
        }

        foreach ($segments as $segment) {
            if ($segment === '' || preg_match('/^[A-Za-z0-9_-]+$/', $segment) !== 1) {
                throw new InvalidSupabaseToken('malformed');
            }
        }

        $json = base64_decode(strtr($segments[0], '-_', '+/').str_repeat('=', (4 - strlen($segments[0]) % 4) % 4), true);
        $header = $json === false ? null : json_decode($json, true);

        if (! is_array($header) || ! is_string($header['alg'] ?? null)) {
            throw new InvalidSupabaseToken('malformed');
        }

        foreach (self::FORBIDDEN_HEADERS as $name) {
            if (array_key_exists($name, $header)) {
                throw new InvalidSupabaseToken('unsupported_header');
            }
        }

        return $header;
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: Key, 1: ?string}
     */
    private function selectKey(array $header): array
    {
        switch ($header['alg']) {
            case 'ES256':
                $kid = $header['kid'] ?? null;

                if (! is_string($kid) || $kid === '' || strlen($kid) > 128) {
                    throw new InvalidSupabaseToken('missing_kid');
                }

                return [$this->jwks->keyFor($kid), $kid];

            case 'HS256':
                $secret = SupabaseConfig::legacySecret();

                if ($secret === null) {
                    throw new InvalidSupabaseToken('alg_not_allowed');
                }

                return [new Key($secret, 'HS256'), null];

            default:
                throw new InvalidSupabaseToken('alg_not_allowed');
        }
    }

    /** @return array<string, mixed> */
    private function decode(string $token, Key $key): array
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = SupabaseConfig::leeway();

        try {
            // A single Key is passed, so the library also checks the header alg equals the key's algorithm.
            $decoded = JWT::decode($token, $key);
        } catch (ExpiredException $e) {
            throw new InvalidSupabaseToken('expired', $e);
        } catch (BeforeValidException $e) {
            throw new InvalidSupabaseToken('not_yet_valid', $e);
        } catch (SignatureInvalidException $e) {
            throw new InvalidSupabaseToken('bad_signature', $e);
        } catch (Throwable $e) {
            throw new InvalidSupabaseToken('malformed', $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $claims = json_decode((string) json_encode($decoded), true);

        if (! is_array($claims)) {
            throw new InvalidSupabaseToken('malformed');
        }

        return $claims;
    }

    /** @param  array<string, mixed>  $claims */
    private function assertClaims(array $claims, string $issuer): void
    {
        if (! isset($claims['exp']) || ! is_numeric($claims['exp'])) {
            throw new InvalidSupabaseToken('missing_exp');
        }

        if (! is_string($claims['iss'] ?? null) || ! hash_equals($issuer, $claims['iss'])) {
            throw new InvalidSupabaseToken('bad_issuer');
        }

        $audiences = $claims['aud'] ?? null;
        $audiences = is_string($audiences) ? [$audiences] : (is_array($audiences) ? $audiences : []);

        if (! in_array(SupabaseConfig::audience(), $audiences, true)) {
            throw new InvalidSupabaseToken('bad_audience');
        }

        // The top-level "role" is the Postgres role. An anon-key token has "anon"; only signed-in users have "authenticated".
        if (($claims['role'] ?? null) !== 'authenticated') {
            throw new InvalidSupabaseToken('bad_role_claim');
        }

        if (($claims['is_anonymous'] ?? false) === true) {
            throw new InvalidSupabaseToken('anonymous');
        }

        $subject = $claims['sub'] ?? null;

        if (! is_string($subject) || preg_match(self::UUID, $subject) !== 1 || $subject === self::NIL_UUID) {
            throw new InvalidSupabaseToken('bad_subject');
        }
    }
}
