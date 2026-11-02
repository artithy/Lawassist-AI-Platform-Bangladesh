<?php

namespace Tests\Feature\Api;

use App\Support\ApiRateLimit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\ConfiguresSupabase;
use Tests\Support\OverridesEnvironment;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

/** Rate limiting of the /api routes: per client address and per Authorization header, 429 in the standard envelope. */
class RateLimitTest extends TestCase
{
    use ConfiguresSupabase;
    use OverridesEnvironment;
    use RefreshDatabase;

    private const TOO_MANY = ['success' => false, 'message' => 'Too many requests. Please try again later.'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();
        config(['api.rate_limit.per_ip' => 1000, 'api.rate_limit.per_token' => 1000]);
    }

    private function fromAddress(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function bearer(): array
    {
        $sub = (string) Str::uuid();

        return ['Authorization' => 'Bearer '.$this->keys->sign(SupabaseTestKeys::claims(['sub' => $sub, 'email' => "u{$sub}@example.com"]))];
    }

    // ---- wiring ----------------------------------------------------------------------------------------------

    /** @return list<string> the middleware the router will really run for a route, in order, aliases and groups expanded */
    private function middlewareOf(\Illuminate\Routing\Route $route): array
    {
        return array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
    }

    public function test_every_api_route_is_behind_the_limiter(): void
    {
        $api = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));

        $this->assertNotEmpty($api);
        foreach ($api as $route) {
            $this->assertContains(
                'Illuminate\\Routing\\Middleware\\ThrottleRequests:'.ApiRateLimit::NAME,
                $this->middlewareOf($route),
                $route->uri().' is not throttled',
            );
        }
    }

    public function test_the_limiter_runs_before_authentication(): void
    {
        // Otherwise a flood of missing or invalid tokens would be answered 401 and never counted.
        $order = $this->middlewareOf(Route::getRoutes()->match(Request::create('/api/me', 'GET')));

        $throttle = $this->positionOf($order, 'Illuminate\\Routing\\Middleware\\ThrottleRequests');
        $auth = $this->positionOf($order, 'Illuminate\\Auth\\Middleware\\Authenticate');

        $this->assertNotNull($throttle, 'no throttle on /api/me');
        $this->assertNotNull($auth, 'no authentication on /api/me');
        $this->assertLessThan($auth, $throttle, 'the throttle must run before authentication');
    }

    /** @param  list<string>  $order */
    private function positionOf(array $order, string $class): ?int
    {
        foreach ($order as $position => $middleware) {
            if ($middleware === $class || str_starts_with($middleware, $class.':')) {
                return $position;
            }
        }

        return null;
    }

    public function test_successful_responses_report_the_allowance(): void
    {
        config(['api.rate_limit.per_ip' => 50]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '50')
            ->assertHeader('X-RateLimit-Remaining', '49');
    }

    // ---- per client address ----------------------------------------------------------------------------------

    public function test_the_per_address_limit_answers_429_in_the_standard_envelope(): void
    {
        config(['api.rate_limit.per_ip' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->fromAddress('10.1.1.1')->getJson('/api/health')->assertOk();
        }

        $response = $this->fromAddress('10.1.1.1')->getJson('/api/health');

        $response->assertStatus(429)->assertExactJson(self::TOO_MANY);
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60, (int) $response->headers->get('Retry-After'));
    }

    public function test_another_address_has_its_own_allowance(): void
    {
        config(['api.rate_limit.per_ip' => 2]);

        $this->fromAddress('10.2.2.2')->getJson('/api/health')->assertOk();
        $this->fromAddress('10.2.2.2')->getJson('/api/health')->assertOk();
        $this->fromAddress('10.2.2.2')->getJson('/api/health')->assertStatus(429);

        $this->fromAddress('10.3.3.3')->getJson('/api/health')->assertOk();
    }

    public function test_unauthenticated_and_rejected_requests_count_too(): void
    {
        config(['api.rate_limit.per_ip' => 3]);

        $this->fromAddress('10.4.4.4')->getJson('/api/me')->assertStatus(401);
        $this->fromAddress('10.4.4.4')->getJson('/api/me', ['Authorization' => 'Bearer not-a-jwt'])->assertStatus(401);
        $this->fromAddress('10.4.4.4')->getJson('/api/me', ['Authorization' => 'Basic abc'])->assertStatus(401);
        $this->fromAddress('10.4.4.4')->getJson('/api/me')->assertStatus(429);
    }

    // ---- per signed-in client --------------------------------------------------------------------------------

    public function test_the_per_token_limit_is_separate_for_each_authorization_header(): void
    {
        config(['api.rate_limit.per_token' => 2]);
        $first = $this->bearer();
        $second = $this->bearer();

        $this->fromAddress('10.5.5.5')->getJson('/api/me', $first)->assertOk();
        $this->fromAddress('10.5.5.5')->getJson('/api/me', $first)->assertOk();
        $this->fromAddress('10.5.5.5')->getJson('/api/me', $first)->assertStatus(429)->assertExactJson(self::TOO_MANY);

        // Same address, another client, and an anonymous caller are unaffected.
        $this->fromAddress('10.5.5.5')->getJson('/api/me', $second)->assertOk();
        $this->fromAddress('10.5.5.5')->getJson('/api/health')->assertOk();
    }

    public function test_the_same_token_from_a_new_address_still_shares_one_token_allowance(): void
    {
        config(['api.rate_limit.per_token' => 2]);
        $token = $this->bearer();

        $this->fromAddress('10.6.6.1')->getJson('/api/me', $token)->assertOk();
        $this->fromAddress('10.6.6.2')->getJson('/api/me', $token)->assertOk();
        $this->fromAddress('10.6.6.3')->getJson('/api/me', $token)->assertStatus(429);
    }

    public function test_changing_the_header_does_not_get_around_the_address_limit(): void
    {
        config(['api.rate_limit.per_ip' => 3, 'api.rate_limit.per_token' => 1000]);

        foreach (['Bearer a.a.a', 'Bearer b.b.b', 'Bearer c.c.c'] as $header) {
            $this->fromAddress('10.7.7.7')->getJson('/api/me', ['Authorization' => $header])->assertStatus(401);
        }

        $this->fromAddress('10.7.7.7')->getJson('/api/me', ['Authorization' => 'Bearer d.d.d'])->assertStatus(429);
    }

    // ---- 429 is readable and CORS-friendly -------------------------------------------------------------------

    public function test_a_429_carries_cors_headers_and_exposes_retry_after_to_the_frontend(): void
    {
        config(['api.rate_limit.per_ip' => 1, 'cors.allowed_origins' => ['http://localhost:5173']]);

        $this->fromAddress('10.8.8.8')->getJson('/api/health', ['Origin' => 'http://localhost:5173'])->assertOk();

        $response = $this->fromAddress('10.8.8.8')->getJson('/api/health', ['Origin' => 'http://localhost:5173']);

        $response->assertStatus(429)->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertStringContainsString('Retry-After', (string) $response->headers->get('Access-Control-Expose-Headers'));
    }

    public function test_cors_preflights_are_answered_before_the_limiter(): void
    {
        config(['api.rate_limit.per_ip' => 1, 'cors.allowed_origins' => ['http://localhost:5173']]);

        $this->fromAddress('10.9.9.9')->getJson('/api/health')->assertOk();
        $this->fromAddress('10.9.9.9')->getJson('/api/health')->assertStatus(429);

        $this->fromAddress('10.9.9.9')->call('OPTIONS', '/api/me', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])->assertStatus(204);
    }

    // ---- the limits themselves -------------------------------------------------------------------------------

    public function test_the_authorization_header_is_hashed_and_never_part_of_a_limit_key(): void
    {
        $secret = 'Bearer secret-marker-aaa.secret-marker-bbb.secret-marker-ccc';
        $request = Request::create('/api/me', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => $secret, 'REMOTE_ADDR' => '192.0.2.10']);

        $limits = ApiRateLimit::limits($request);

        $this->assertCount(2, $limits);
        $this->assertContainsOnlyInstancesOf(Limit::class, $limits);
        foreach ($limits as $limit) {
            $this->assertStringNotContainsString('secret-marker', $limit->key);
        }
        $this->assertSame('authorization:'.hash('sha256', $secret), $limits[1]->key);
        $this->assertSame('ip:192.0.2.10', $limits[0]->key);
    }

    public function test_without_an_authorization_header_only_the_address_limit_applies(): void
    {
        $limits = ApiRateLimit::limits(Request::create('/api/health', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.11']));

        $this->assertCount(1, $limits);
        $this->assertSame('ip:192.0.2.11', $limits[0]->key);
    }

    public function test_the_configured_numbers_are_used(): void
    {
        config(['api.rate_limit.per_ip' => 7, 'api.rate_limit.per_token' => 5]);
        $request = Request::create('/api/me', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']);

        [$perIp, $perToken] = ApiRateLimit::limits($request);

        $this->assertSame(7, $perIp->maxAttempts);
        $this->assertSame(5, $perToken->maxAttempts);
        $this->assertSame(60, $perIp->decaySeconds);
    }

    public function test_a_bad_setting_falls_back_to_the_default_instead_of_disabling_or_blocking(): void
    {
        $request = Request::create('/api/me', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']);

        foreach ([0, -5, '0', '-1', 'abc', '', ' ', null, false, 1.5, '1.5', '10 000', []] as $bad) {
            config(['api.rate_limit.per_ip' => $bad, 'api.rate_limit.per_token' => $bad]);

            [$perIp, $perToken] = ApiRateLimit::limits($request);

            $this->assertSame(ApiRateLimit::DEFAULT_PER_IP, $perIp->maxAttempts, 'per_ip for '.var_export($bad, true));
            $this->assertSame(ApiRateLimit::DEFAULT_PER_TOKEN, $perToken->maxAttempts, 'per_token for '.var_export($bad, true));
        }
    }

    public function test_numeric_strings_from_the_environment_are_accepted(): void
    {
        config(['api.rate_limit.per_ip' => '450', 'api.rate_limit.per_token' => ' 90 ']);
        $request = Request::create('/api/me', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']);

        [$perIp, $perToken] = ApiRateLimit::limits($request);

        $this->assertSame(450, $perIp->maxAttempts);
        $this->assertSame(90, $perToken->maxAttempts);
    }

    // ---- shipped defaults: strict in production, relaxed for development ---------------------------------------

    public function test_the_shipped_defaults_are_strict_in_production_and_relaxed_in_development(): void
    {
        $production = $this->configFileUnder('api', ['APP_ENV' => 'production', 'API_RATE_LIMIT_PER_IP' => null, 'API_RATE_LIMIT_PER_TOKEN' => null]);
        $unset = $this->configFileUnder('api', ['APP_ENV' => null, 'API_RATE_LIMIT_PER_IP' => null, 'API_RATE_LIMIT_PER_TOKEN' => null]);
        $staging = $this->configFileUnder('api', ['APP_ENV' => 'staging', 'API_RATE_LIMIT_PER_IP' => null, 'API_RATE_LIMIT_PER_TOKEN' => null]);
        $local = $this->configFileUnder('api', ['APP_ENV' => 'local', 'API_RATE_LIMIT_PER_IP' => null, 'API_RATE_LIMIT_PER_TOKEN' => null]);
        $testing = $this->configFileUnder('api', ['APP_ENV' => 'testing', 'API_RATE_LIMIT_PER_IP' => null, 'API_RATE_LIMIT_PER_TOKEN' => null]);

        foreach ([$production, $unset, $staging] as $strict) {
            $this->assertSame(300, $strict['rate_limit']['per_ip']);
            $this->assertSame(120, $strict['rate_limit']['per_token']);
        }
        foreach ([$local, $testing] as $relaxed) {
            $this->assertSame(3000, $relaxed['rate_limit']['per_ip']);
            $this->assertSame(1200, $relaxed['rate_limit']['per_token']);
        }
        $this->assertSame(ApiRateLimit::DEFAULT_PER_IP, $production['rate_limit']['per_ip']);
        $this->assertSame(ApiRateLimit::DEFAULT_PER_TOKEN, $production['rate_limit']['per_token']);
    }

    public function test_the_environment_can_override_the_shipped_numbers(): void
    {
        $config = $this->configFileUnder('api', ['APP_ENV' => 'production', 'API_RATE_LIMIT_PER_IP' => '50', 'API_RATE_LIMIT_PER_TOKEN' => '25']);

        $this->assertSame('50', $config['rate_limit']['per_ip']);
        $this->assertSame('25', $config['rate_limit']['per_token']);
    }

    public function test_normal_development_use_does_not_hit_the_limit(): void
    {
        // The real shipped development allowance (config/api.php under APP_ENV=local), not the test override.
        config(['api' => $this->configFileUnder('api', ['APP_ENV' => 'local', 'API_RATE_LIMIT_PER_IP' => null, 'API_RATE_LIMIT_PER_TOKEN' => null])]);
        $token = $this->bearer();

        for ($i = 0; $i < 150; $i++) { // far more than a hot-reloading React dev session sends in a minute
            $this->getJson('/api/me', $token)->assertOk();
        }
    }
}
