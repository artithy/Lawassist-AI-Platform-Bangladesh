<?php

namespace Tests\Unit\Services\AI;

use PHPUnit\Framework\TestCase;

/** The real-Ollama test must never be part of the normal automated run. */
class OllamaIntegrationIsolationTest extends TestCase
{
    public function test_phpunit_runs_only_the_unit_and_feature_directories(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 4).'/phpunit.xml');
        $directories = array_map('strval', $xml->xpath('//testsuites//directory') ?: []);
        sort($directories);

        $this->assertSame(['tests/Feature', 'tests/Unit'], $directories);
        $this->assertFileExists(dirname(__DIR__, 4).'/tests/Integration/OllamaIntegrationTest.php');
    }

    public function test_the_flag_is_not_set_by_phpunit_xml(): void
    {
        $this->assertStringNotContainsString('RUN_OLLAMA_INTEGRATION_TESTS', (string) file_get_contents(dirname(__DIR__, 4).'/phpunit.xml'));
    }

    public function test_the_integration_test_is_off_unless_explicitly_enabled(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4).'/tests/Integration/OllamaIntegrationTest.php');

        $this->assertStringContainsString("env('RUN_OLLAMA_INTEGRATION_TESTS', false)", $source); // default: off
        $this->assertStringContainsString('markTestSkipped', $source);
    }
}
