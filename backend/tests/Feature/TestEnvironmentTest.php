<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The suite runs in the test environment whatever the machine's own environment looks like.
 *
 * This failed under Docker, where docker-compose.yml's env_file turns every .env value into a real environment variable and
 * PHPUnit does not replace an existing variable unless the <env> entry says force="true".
 */
class TestEnvironmentTest extends TestCase
{
    public function test_the_application_is_in_the_testing_environment_on_throw_away_services(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('array', config('mail.default'));
    }

    public function test_every_test_setting_in_phpunit_xml_is_forced_and_repeated_for_server(): void
    {
        $xml = simplexml_load_file(base_path('phpunit.xml'));
        $env = [];
        $server = [];

        foreach ($xml->xpath('//php/env') ?: [] as $entry) {
            $env[(string) $entry['name']] = (string) $entry['value'];
            $this->assertSame('true', (string) $entry['force'], ((string) $entry['name']).' must have force="true"');
        }

        foreach ($xml->xpath('//php/server') ?: [] as $entry) {
            $server[(string) $entry['name']] = (string) $entry['value'];
        }

        // Laravel reads $_SERVER before $_ENV, and PHPUnit only updates $_SERVER through <server>.
        $this->assertSame($env, $server, 'Every <env> setting needs the same <server> setting.');

        foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'MAIL_MAILER'] as $required) {
            $this->assertArrayHasKey($required, $env, "{$required} must be set (and forced) for the tests");
        }
    }

    public function test_the_values_really_reach_every_place_laravel_reads_not_only_the_config(): void
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array'] as $name => $value) {
            $this->assertSame($value, getenv($name), "getenv({$name})");
            $this->assertSame($value, $_ENV[$name] ?? null, "\$_ENV[{$name}]");
            $this->assertSame($value, $_SERVER[$name] ?? null, "\$_SERVER[{$name}]");
        }
    }
}
