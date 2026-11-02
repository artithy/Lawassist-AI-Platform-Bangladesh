<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/** CORS for /api/*: only the configured frontend origin, only the needed methods and headers, no wildcard, no cookies. */
class CorsTest extends TestCase
{
    private const FRONTEND = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cors.allowed_origins' => [self::FRONTEND]]); // HandleCors re-reads the config on every request
    }

    /** @param  array<string, string>  $extra */
    private function preflight(string $origin, string $method = 'GET', string $headers = 'authorization', string $uri = '/api/health', array $extra = [])
    {
        return $this->options($uri, [], array_merge([
            'Origin' => $origin,
            'Access-Control-Request-Method' => $method,
            'Access-Control-Request-Headers' => $headers,
        ], $extra));
    }

    // ---- the shipped configuration ---------------------------------------------------------------------------

    public function test_the_shipped_configuration_is_narrow(): void
    {
        $config = require base_path('config/cors.php');

        $this->assertSame(['api/*'], $config['paths']);
        $this->assertSame(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $config['allowed_methods']);
        $this->assertSame(['Authorization', 'Content-Type', 'Accept'], $config['allowed_headers']);
        $this->assertFalse($config['supports_credentials']);
        $this->assertSame([], $config['allowed_origins_patterns']);
        $this->assertSame(['Retry-After', 'WWW-Authenticate'], $config['exposed_headers']);
        $this->assertNotContains('*', $config['allowed_origins']);
        $this->assertNotContains('*', $config['allowed_methods']);
        $this->assertNotContains('*', $config['allowed_headers']);
    }

    // ---- actual requests -------------------------------------------------------------------------------------

    public function test_the_frontend_origin_may_read_api_responses(): void
    {
        $this->getJson('/api/health', ['Origin' => self::FRONTEND])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_the_header_is_never_a_wildcard_and_never_a_foreign_origin(): void
    {
        // With a single configured origin the CORS library always answers with that fixed origin (valid per the spec):
        // a browser then refuses every other site. What must never happen is "*" or echoing the caller's own origin.
        foreach ([self::FRONTEND, 'http://evil.example.com', 'null', null] as $origin) {
            $response = $this->getJson('/api/health', $origin === null ? [] : ['Origin' => $origin]);

            $this->assertContains($response->headers->get('Access-Control-Allow-Origin'), [null, self::FRONTEND]);
            $response->assertHeaderMissing('Access-Control-Allow-Credentials');
        }
    }

    public function test_with_several_origins_only_the_matching_one_is_echoed_and_responses_vary_on_origin(): void
    {
        config(['cors.allowed_origins' => [self::FRONTEND, 'https://app.example.com']]);

        $allowed = $this->getJson('/api/health', ['Origin' => 'https://app.example.com']);
        $allowed->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
        $this->assertStringContainsString('Origin', (string) $allowed->headers->get('Vary'));

        foreach (['http://evil.example.com', 'http://localhost:3000', 'https://localhost:5173', 'http://localhost:5173.evil.com', 'null'] as $origin) {
            $this->getJson('/api/health', ['Origin' => $origin])->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    public function test_error_responses_carry_cors_headers_so_the_frontend_can_read_them(): void
    {
        $this->getJson('/api/me', ['Origin' => self::FRONTEND])
            ->assertStatus(401)
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="LawAssist API"');
    }

    public function test_retry_after_and_www_authenticate_are_readable_by_the_frontend(): void
    {
        $unauthenticated = $this->getJson('/api/me', ['Origin' => self::FRONTEND]);

        $exposed = array_map('trim', explode(',', strtolower((string) $unauthenticated->headers->get('Access-Control-Expose-Headers'))));
        $this->assertContains('retry-after', $exposed);
        $this->assertContains('www-authenticate', $exposed);
        $this->assertNotContains('*', $exposed);
        $this->assertSame('Bearer realm="LawAssist API"', $unauthenticated->headers->get('WWW-Authenticate'));
    }

    public function test_paths_outside_the_api_are_not_cors_enabled(): void
    {
        $this->get('/', ['Origin' => self::FRONTEND])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    // ---- preflight -------------------------------------------------------------------------------------------

    public function test_a_preflight_from_the_frontend_is_approved_for_the_headers_it_needs(): void
    {
        $response = $this->preflight(self::FRONTEND, 'GET', 'authorization,content-type,accept');

        $response->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND)
            ->assertHeader('Access-Control-Max-Age', '600')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        foreach (['authorization', 'content-type', 'accept'] as $header) {
            $this->assertStringContainsString($header, $allowedHeaders);
        }
    }

    public function test_the_preflight_lists_only_the_necessary_methods(): void
    {
        $methods = array_map('trim', explode(',', (string) $this->preflight(self::FRONTEND, 'POST')->headers->get('Access-Control-Allow-Methods')));

        $this->assertEqualsCanonicalizing(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $methods);
        $this->assertNotContains('*', $methods);
        $this->assertNotContains('OPTIONS', $methods);
        $this->assertNotContains('TRACE', $methods);
    }

    public function test_every_allowed_method_passes_the_preflight(): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->preflight(self::FRONTEND, $method)->assertHeader('Access-Control-Allow-Origin', self::FRONTEND);
        }
    }

    public function test_other_methods_are_not_approved(): void
    {
        foreach (['TRACE', 'CONNECT', 'PROPFIND'] as $method) {
            $allowed = (string) $this->preflight(self::FRONTEND, $method)->headers->get('Access-Control-Allow-Methods');

            $this->assertStringNotContainsString($method, $allowed); // the browser compares its method with this list
        }
    }

    public function test_other_request_headers_are_not_approved(): void
    {
        $response = $this->preflight(self::FRONTEND, 'GET', 'authorization,x-custom-header');

        $this->assertStringNotContainsString('x-custom-header', strtolower((string) $response->headers->get('Access-Control-Allow-Headers')));
    }

    public function test_a_preflight_from_another_origin_is_not_approved(): void
    {
        $response = $this->preflight('http://evil.example.com');

        $this->assertNotSame('http://evil.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));

        config(['cors.allowed_origins' => [self::FRONTEND, 'https://app.example.com']]);
        $this->preflight('http://evil.example.com')
            ->assertHeaderMissing('Access-Control-Allow-Origin')
            ->assertHeaderMissing('Access-Control-Allow-Methods')
            ->assertHeaderMissing('Access-Control-Allow-Headers');
    }

    public function test_the_preflight_never_needs_authentication(): void
    {
        $this->preflight(self::FRONTEND, 'GET', 'authorization', '/api/me')
            ->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND);
    }

    // ---- configuration of origins ----------------------------------------------------------------------------

    public function test_several_configured_origins_are_each_allowed(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173', 'https://app.example.com']]);

        $this->getJson('/api/health', ['Origin' => 'https://app.example.com'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
        $this->getJson('/api/health', ['Origin' => 'http://localhost:5173'])
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $this->getJson('/api/health', ['Origin' => 'https://other.example.com'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_with_no_valid_origin_configured_nothing_is_allowed(): void
    {
        config(['cors.allowed_origins' => \App\Support\FrontendOrigins::parse('*, nonsense')]);

        $this->getJson('/api/health', ['Origin' => self::FRONTEND])->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->preflight(self::FRONTEND)->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
