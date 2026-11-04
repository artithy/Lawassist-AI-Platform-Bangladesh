<?php

namespace Tests\Support;

/**
 * The tests must only ever run against the "testing" environment and the throw-away in-memory SQLite database, because many
 * of them use RefreshDatabase, which runs migrate:fresh (it drops every table of the connected database).
 *
 * phpunit.xml sets exactly that, but a real environment variable beats a phpunit.xml setting unless force="true" is given.
 * Under Docker, docker-compose.yml passes backend/.env in as real environment variables, so without the guard a mistake
 * (a missing force attribute, a new setting that is not forced) would silently point the tests at the real database.
 *
 * Only names are ever reported (environment, driver), never hosts, passwords or paths.
 */
final class TestEnvironmentGuard
{
    /** @return string|null what is wrong, or null when it is safe to run */
    public static function problem(string $environment, string $connection, mixed $database): ?string
    {
        if ($environment !== 'testing') {
            return "the application environment is \"{$environment}\", not \"testing\"";
        }

        if ($connection !== 'sqlite') {
            return "the default database connection is \"{$connection}\", not \"sqlite\"";
        }

        if ($database !== ':memory:') {
            return 'the SQLite database is not the in-memory one (:memory:)';
        }

        return null;
    }

    public static function refusal(string $problem): string
    {
        return 'Refusing to run the tests: '.$problem.'. The test settings in phpunit.xml were overridden by real environment '
            .'variables (for example from docker-compose.yml env_file), and running now could wipe a real database. Nothing was run.';
    }
}
