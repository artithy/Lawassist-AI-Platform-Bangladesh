<?php

namespace Tests\Integration;

use App\Services\AI\OllamaException;
use App\Services\AI\OllamaService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Talks to a REAL Ollama server with the real settings (OLLAMA_BASE_URL, OLLAMA_MODEL, ... from the app configuration).
 *
 * This file lives outside tests/Unit and tests/Feature, the only directories phpunit.xml runs, so `php artisan test` and
 * `vendor/bin/phpunit` never pick it up. It is also switched off by default: it skips itself unless the environment
 * variable RUN_OLLAMA_INTEGRATION_TESTS is "true" (or "1"). To run it on purpose, name the file:
 *
 *     RUN_OLLAMA_INTEGRATION_TESTS=true php artisan test tests/Integration/OllamaIntegrationTest.php
 *
 * It sends one harmless question, with no legal or personal text, and prints nothing.
 */
#[Group('integration')]
class OllamaIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_OLLAMA_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Real Ollama test is off. Set RUN_OLLAMA_INTEGRATION_TESTS=true to run it.');
        }
    }

    public function test_a_real_ollama_answers_with_non_empty_text(): void
    {
        try {
            $answer = app(OllamaService::class)->chat([
                ['role' => 'system', 'content' => 'You answer in one short sentence.'],
                ['role' => 'user', 'content' => 'Say hello.'],
            ]);
        } catch (OllamaException $e) {
            // The message is a fixed safe sentence; the reason code says what went wrong (never any content).
            $this->fail('Ollama request failed: '.$e->getMessage().' ['.$e->reason.']'.($e instanceof \App\Services\AI\OllamaHttpError ? ' HTTP '.$e->status : ''));
        }

        $this->assertIsString($answer);
        $this->assertNotSame('', trim($answer), 'Ollama returned an empty answer.');
    }
}
