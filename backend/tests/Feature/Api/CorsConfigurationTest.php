<?php

namespace Tests\Feature\Api;

use Tests\Support\OverridesEnvironment;
use Tests\TestCase;

/** config/cors.php evaluated the way a real deployment would: with different APP_ENV and FRONTEND_ORIGIN values. */
class CorsConfigurationTest extends TestCase
{
    use OverridesEnvironment;

    /** @return list<string> */
    private function origins(?string $appEnv, ?string $frontendOrigin): array
    {
        return $this->configFileUnder('cors', ['APP_ENV' => $appEnv, 'FRONTEND_ORIGIN' => $frontendOrigin])['allowed_origins'];
    }

    public function test_production_without_frontend_origin_allows_no_origin_at_all(): void
    {
        $this->assertSame([], $this->origins('production', null));
    }

    public function test_an_unset_app_env_is_treated_as_production(): void
    {
        $this->assertSame([], $this->origins(null, null));
    }

    public function test_staging_and_other_environments_without_frontend_origin_allow_nothing(): void
    {
        foreach (['staging', 'prod', 'development', 'qa', 'preview'] as $env) {
            $this->assertSame([], $this->origins($env, null), $env);
        }
    }

    public function test_local_and_testing_without_frontend_origin_allow_the_vite_dev_server(): void
    {
        $this->assertSame(['http://localhost:5173'], $this->origins('local', null));
        $this->assertSame(['http://localhost:5173'], $this->origins('testing', null));
    }

    public function test_production_with_frontend_origin_allows_exactly_that_origin(): void
    {
        $this->assertSame(['https://app.example.com'], $this->origins('production', 'https://app.example.com'));
        $this->assertSame(
            ['https://app.example.com', 'https://admin.example.com'],
            $this->origins('production', 'https://app.example.com, https://admin.example.com'),
        );
    }

    public function test_production_never_gets_localhost_unless_it_is_configured_explicitly(): void
    {
        $this->assertNotContains('http://localhost:5173', $this->origins('production', null));
        $this->assertNotContains('http://localhost:5173', $this->origins('production', 'https://app.example.com'));
        $this->assertNotContains('http://localhost:5173', $this->origins('production', ''));
        $this->assertNotContains('http://localhost:5173', $this->origins('production', '*'));
    }

    public function test_an_empty_or_invalid_frontend_origin_allows_nothing_even_locally(): void
    {
        foreach (['', '*', 'http://*.example.com', 'app.example.com', 'https://app.example.com/path'] as $setting) {
            $this->assertSame([], $this->origins('local', $setting), "[{$setting}]");
            $this->assertSame([], $this->origins('production', $setting), "[{$setting}]");
        }
    }

    public function test_the_rest_of_the_shipped_configuration_does_not_depend_on_the_environment(): void
    {
        foreach (['production', 'local', null] as $env) {
            $config = $this->configFileUnder('cors', ['APP_ENV' => $env, 'FRONTEND_ORIGIN' => null]);

            $this->assertSame(['api/*'], $config['paths']);
            $this->assertFalse($config['supports_credentials']);
            $this->assertSame([], $config['allowed_origins_patterns']);
            $this->assertSame(['Retry-After', 'WWW-Authenticate'], $config['exposed_headers']);
        }
    }

    public function test_the_environment_override_helper_restores_everything(): void
    {
        $before = [$_ENV['APP_ENV'] ?? null, getenv('APP_ENV'), env('APP_ENV')];

        $this->origins('production', 'https://x.example.com');

        $this->assertSame($before, [$_ENV['APP_ENV'] ?? null, getenv('APP_ENV'), env('APP_ENV')]);
        $this->assertSame('testing', app()->environment());
    }
}
