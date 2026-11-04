<?php

namespace Tests\Unit\Services\AI\Embeddings;

use PHPUnit\Framework\TestCase;

/** The real-Google test must never be part of the normal automated run, and must never print a key or a vector. */
class GoogleEmbeddingIntegrationIsolationTest extends TestCase
{
    private const FILE = 'tests/Integration/GoogleEmbeddingIntegrationTest.php';

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }

    public function test_phpunit_runs_only_the_unit_and_feature_directories(): void
    {
        $xml = simplexml_load_file($this->root().'/phpunit.xml');
        $directories = array_map('strval', $xml->xpath('//testsuites//directory') ?: []);
        sort($directories);

        $this->assertSame(['tests/Feature', 'tests/Unit'], $directories);
        $this->assertFileExists($this->root().'/'.self::FILE);
    }

    public function test_the_integration_test_is_off_unless_explicitly_enabled(): void
    {
        $source = (string) file_get_contents($this->root().'/'.self::FILE);

        $this->assertStringContainsString("env('RUN_GOOGLE_EMBEDDING_INTEGRATION_TESTS', false)", $source); // default: off
        $this->assertStringContainsString('markTestSkipped', $source);
        $this->assertStringContainsString("#[Group('integration')]", $source);
    }

    public function test_the_integration_test_can_not_print_the_key_or_a_vector(): void
    {
        $source = (string) file_get_contents($this->root().'/'.self::FILE);
        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source) ?? '';

        // No output functions, no reading of the key, and no comparison that would print a whole vector on failure.
        $this->assertDoesNotMatchRegularExpression('/\b(echo|print|print_r|var_dump|var_export|dump|dd|error_log|fwrite|fputs|printf)\b/', $code);
        $this->assertDoesNotMatchRegularExpression('/api_key|GOOGLE_AI_API_KEY|getenv\(/', $code);
        $this->assertDoesNotMatchRegularExpression('/assert(Equals|Same|EqualsWithDelta)\(\s*\$\w*(vector|embedding|batch|single|query|doc)\w*\s*,/i', $code);
    }
}
