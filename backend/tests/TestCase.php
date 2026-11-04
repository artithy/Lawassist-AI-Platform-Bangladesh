<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\TestEnvironmentGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Stops before anything runs (and so before RefreshDatabase can migrate) when the application is not in the test
     * environment on the in-memory database. See TestEnvironmentGuard for why that can happen.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $problem = TestEnvironmentGuard::problem(
            $app->environment(),
            (string) $app['config']->get('database.default'),
            $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database'),
        );

        if ($problem !== null) {
            throw new RuntimeException(TestEnvironmentGuard::refusal($problem));
        }

        return $app;
    }
}
