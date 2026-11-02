<?php

namespace App\Auth\Supabase;

use Illuminate\Http\Request;

/**
 * Reads the access token from the request, strictly.
 *
 * Accepted: exactly one Authorization header of the form "Bearer <jwt>": the scheme in any letter case, a single space,
 * then three base64url segments separated by dots, and nothing else (no extra spaces, no commas, no second value).
 * The token is NEVER read from the query string, the body or cookies.
 *
 * Laravel's own $request->bearerToken() is not used on purpose: it finds "Bearer " anywhere in the header and cuts
 * at a comma, which is too lenient for a security boundary.
 *
 * Results:
 *  - null                  no Bearer credentials were presented (no header, or another scheme such as Basic).
 *                          The guard treats that as "unauthenticated" (401 with a plain Bearer challenge).
 *  - the token string      a well-formed Bearer header.
 *  - InvalidSupabaseToken  a Bearer header that is malformed (401 with error="invalid_token").
 */
final class BearerToken
{
    // \A and \z (not ^ and $): "$" would also match before a trailing newline.
    private const WELL_FORMED = '/\Abearer ([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)\z/i';

    private const BEARER_SCHEME = '/\Abearer(?:[ \t]|\z)/i';

    /**
     * @throws InvalidSupabaseToken when a Bearer header is present but malformed
     */
    public static function from(Request $request): ?string
    {
        $headers = $request->headers->all('authorization');

        if ($headers === []) {
            return null;
        }

        if (count($headers) !== 1) {
            throw new InvalidSupabaseToken('malformed_authorization'); // several Authorization values: ambiguous
        }

        $header = $headers[0];

        if (! is_string($header) || preg_match(self::BEARER_SCHEME, $header) !== 1) {
            return null; // another scheme (Basic, Digest, ...) or an empty header: no Bearer credentials
        }

        if (preg_match(self::WELL_FORMED, $header, $matches) !== 1) {
            throw new InvalidSupabaseToken('malformed_authorization');
        }

        return $matches[1];
    }
}
