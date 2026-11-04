<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestEnvironmentGuard;

/** The guard that stops the suite from running against anything but the in-memory test database. Plain PHPUnit: no application. */
class TestEnvironmentGuardTest extends TestCase
{
    public function test_the_testing_environment_on_in_memory_sqlite_is_allowed(): void
    {
        $this->assertNull(TestEnvironmentGuard::problem('testing', 'sqlite', ':memory:'));
    }

    /** @return array<string, array{string, string, mixed, string}> */
    public static function unsafe(): array
    {
        return [
            'the local environment' => ['local', 'sqlite', ':memory:', 'environment is "local"'],
            'production' => ['production', 'sqlite', ':memory:', 'environment is "production"'],
            'an empty environment' => ['', 'sqlite', ':memory:', 'environment is ""'],
            'a different case' => ['Testing', 'sqlite', ':memory:', 'environment is "Testing"'],
            'a Postgres connection (the real database)' => ['testing', 'pgsql', ':memory:', 'connection is "pgsql"'],
            'a MySQL connection' => ['testing', 'mysql', ':memory:', 'connection is "mysql"'],
            'an unknown connection' => ['testing', '', ':memory:', 'connection is ""'],
            'a SQLite file' => ['testing', 'sqlite', 'database/database.sqlite', 'not the in-memory one'],
            'a SQLite file with an absolute path' => ['testing', 'sqlite', '/var/www/database/database.sqlite', 'not the in-memory one'],
            'no database name' => ['testing', 'sqlite', null, 'not the in-memory one'],
            'an empty database name' => ['testing', 'sqlite', '', 'not the in-memory one'],
            'a look-alike' => ['testing', 'sqlite', ':memory: ', 'not the in-memory one'],
        ];
    }

    #[DataProvider('unsafe')]
    public function test_anything_else_is_refused_and_named(string $environment, string $connection, mixed $database, string $expected): void
    {
        $problem = TestEnvironmentGuard::problem($environment, $connection, $database);

        $this->assertNotNull($problem);
        $this->assertStringContainsString($expected, $problem);
    }

    public function test_the_environment_is_checked_before_the_database(): void
    {
        $this->assertStringContainsString('environment', (string) TestEnvironmentGuard::problem('local', 'pgsql', 'postgres'));
    }

    public function test_the_refusal_explains_what_happened_and_never_shows_hosts_or_paths(): void
    {
        $problem = (string) TestEnvironmentGuard::problem('testing', 'pgsql', 'db.example.supabase.co');
        $message = TestEnvironmentGuard::refusal($problem);

        $this->assertStringStartsWith('Refusing to run the tests:', $message);
        $this->assertStringContainsString('env_file', $message);
        $this->assertStringContainsString('Nothing was run.', $message);
        $this->assertStringNotContainsString('supabase', $message);
    }
}
