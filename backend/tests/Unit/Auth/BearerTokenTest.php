<?php

namespace Tests\Unit\Auth;

use App\Auth\Supabase\BearerToken;
use App\Auth\Supabase\InvalidSupabaseToken;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * BearerToken must accept exactly "Authorization: Bearer <jwt-shaped token>" and nothing looser.
 * (Pure unit test: it needs no application, only a Request.)
 */
class BearerTokenTest extends TestCase
{
    private const TOKEN = 'eyJhbGciOiJFUzI1NiJ9.eyJzdWIiOiIxIn0.c2ln-_nature9';

    /** @param  array<string, string>  $server */
    private function makeRequest(array $server = [], string $uri = '/api/me'): Request
    {
        return Request::create($uri, 'GET', [], [], [], $server);
    }

    private function withAuthorization(string $value): Request
    {
        return $this->makeRequest(['HTTP_AUTHORIZATION' => $value]);
    }

    private function assertMalformed(Request $request): void
    {
        try {
            BearerToken::from($request);
            $this->fail('Expected InvalidSupabaseToken.');
        } catch (InvalidSupabaseToken $e) {
            $this->assertSame('malformed_authorization', $e->reason);
        }
    }

    public function test_a_well_formed_bearer_header_returns_the_token(): void
    {
        $this->assertSame(self::TOKEN, BearerToken::from($this->withAuthorization('Bearer '.self::TOKEN)));
    }

    public function test_the_scheme_is_case_insensitive(): void
    {
        foreach (['bearer', 'BEARER', 'BeArEr'] as $scheme) {
            $this->assertSame(self::TOKEN, BearerToken::from($this->withAuthorization("{$scheme} ".self::TOKEN)));
        }
    }

    public function test_no_header_means_no_credentials(): void
    {
        $this->assertNull(BearerToken::from($this->makeRequest()));
    }

    public function test_another_scheme_means_no_credentials(): void
    {
        foreach (['Basic dXNlcjpwYXNz', 'Digest realm="x"', 'Token '.self::TOKEN, 'Bearerx '.self::TOKEN, 'Negotiate abc'] as $value) {
            $this->assertNull(BearerToken::from($this->withAuthorization($value)), $value);
        }
    }

    public function test_an_empty_authorization_header_means_no_credentials(): void
    {
        $this->assertNull(BearerToken::from($this->withAuthorization('')));
    }

    public function test_a_bearer_header_without_a_token_is_malformed(): void
    {
        $this->assertMalformed($this->withAuthorization('Bearer'));
        $this->assertMalformed($this->withAuthorization('Bearer '));
    }

    public function test_extra_whitespace_is_malformed(): void
    {
        $this->assertMalformed($this->withAuthorization('Bearer  '.self::TOKEN));       // two spaces
        $this->assertMalformed($this->withAuthorization("Bearer\t".self::TOKEN));        // tab instead of a space
        $this->assertMalformed($this->withAuthorization('Bearer '.self::TOKEN.' '));     // trailing space
        $this->assertMalformed($this->withAuthorization('Bearer '.self::TOKEN."\n"));    // trailing newline
    }

    public function test_a_token_that_is_not_jwt_shaped_is_malformed(): void
    {
        foreach ([
            'abc',                       // one segment
            'a.b',                       // two segments
            'a.b.c.d',                   // four segments
            'a..c',                      // empty segment
            'a.b.c d',                   // space inside
            'a.b.c,d.e.f',               // comma
            'a.b.c;x',                   // semicolon
            'a+b.c.d',                   // not base64url
            'a/b.c.d',                   // not base64url
            'a=b.c.d',                   // padding is not allowed in JWT segments
            'Bearer a.b.c',              // a second "Bearer"
        ] as $token) {
            $this->assertMalformed($this->withAuthorization('Bearer '.$token));
        }
    }

    public function test_bearer_text_hidden_inside_another_value_is_not_found(): void
    {
        // Laravel's bearerToken() would extract the token from these; the strict parser must not.
        $this->assertNull(BearerToken::from($this->withAuthorization('Basic x Bearer '.self::TOKEN)));
        $this->assertNull(BearerToken::from($this->withAuthorization('Foo Bearer '.self::TOKEN)));
    }

    public function test_several_authorization_values_are_malformed(): void
    {
        $request = $this->makeRequest();
        $request->headers->set('Authorization', ['Bearer '.self::TOKEN, 'Bearer '.self::TOKEN]);

        $this->assertMalformed($request);
    }

    public function test_the_token_is_never_read_from_the_query_string_body_or_cookies(): void
    {
        $request = Request::create(
            '/api/me?access_token='.self::TOKEN.'&token='.self::TOKEN,
            'POST',
            ['access_token' => self::TOKEN, 'token' => self::TOKEN],
            ['access_token' => self::TOKEN, 'token' => self::TOKEN],
        );

        $this->assertNull(BearerToken::from($request));
    }
}
