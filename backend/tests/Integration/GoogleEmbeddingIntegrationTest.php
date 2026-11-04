<?php

namespace Tests\Integration;

use App\Services\AI\Embeddings\EmbeddingException;
use App\Services\AI\Embeddings\EmbeddingHttpError;
use App\Services\AI\Embeddings\EmbeddingRateLimited;
use App\Services\AI\Embeddings\EmbeddingService;
use App\Services\AI\Embeddings\EmbeddingVector;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Talks to the REAL Google Gemini API with your real key (GOOGLE_AI_API_KEY in backend/.env) and the real settings from
 * config/google_ai.php.
 *
 * This file lives outside tests/Unit and tests/Feature, the only directories phpunit.xml runs, so `php artisan test` and
 * `vendor/bin/phpunit` never pick it up. It is also switched off by default: it skips itself unless the environment
 * variable RUN_GOOGLE_EMBEDDING_INTEGRATION_TESTS is "true" (or "1"). To run it on purpose, name the file:
 *
 *     RUN_GOOGLE_EMBEDDING_INTEGRATION_TESTS=true php artisan test tests/Integration/GoogleEmbeddingIntegrationTest.php
 *
 * It makes four small requests (the free tier is enough) with short, public, non-confidential Bengali sentences. It never
 * prints the key or a vector: it checks sizes, numbers and similarities, and on a failure it reports only a fixed message,
 * a reason code and the HTTP status.
 */
#[Group('integration')]
class GoogleEmbeddingIntegrationTest extends TestCase
{
    private const QUERY = 'চুরির শাস্তি কী?'; // "What is the punishment for theft?"

    private const RELEVANT = 'দণ্ডবিধির ৩৭৯ ধারা অনুযায়ী চুরির শাস্তি তিন বছর পর্যন্ত কারাদণ্ড, বা অর্থদণ্ড, বা উভয়দণ্ড।'; // Penal Code s.379

    private const UNRELATED = 'আজ ঢাকায় আকাশ মেঘলা থাকবে এবং বিকেলে বৃষ্টি হতে পারে।'; // "Dhaka will be cloudy today; rain this afternoon."

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_GOOGLE_EMBEDDING_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Real Google embedding test is off. Set RUN_GOOGLE_EMBEDDING_INTEGRATION_TESTS=true to run it.');
        }
    }

    /** Runs a call and turns a service failure into a test failure that names only the fixed message and the codes. */
    private function safely(callable $call): mixed
    {
        try {
            return $call();
        } catch (EmbeddingException $e) {
            $detail = $e->getMessage().' ['.$e->reason.']';

            if ($e instanceof EmbeddingHttpError) {
                $detail .= ' HTTP '.$e->status.($e->googleStatus !== null ? ' '.$e->googleStatus : '');
            }

            if ($e instanceof EmbeddingRateLimited && $e->retryAfterSeconds !== null) {
                $detail .= ' retry after '.$e->retryAfterSeconds.'s';
            }

            $this->fail('Google embedding request failed: '.$detail);
        }
    }

    private function assertGoodVector(mixed $vector, int $size): void
    {
        $this->assertIsArray($vector);
        $this->assertSame($size, count($vector), 'The vector does not have the configured number of values.');
        $this->assertTrue(array_is_list($vector));

        $allFinite = true;
        $anyNonZero = false;

        foreach ($vector as $value) {
            $allFinite = $allFinite && is_float($value) && is_finite($value);
            $anyNonZero = $anyNonZero || $value !== 0.0;
        }

        $this->assertTrue($allFinite, 'The vector holds a value that is not a finite number.');
        $this->assertTrue($anyNonZero, 'The vector is all zeros.');
        $this->assertTrue(EmbeddingVector::isUnitLength($vector, 1e-6), 'The vector does not have length 1.');
    }

    public function test_a_real_bengali_text_gives_a_valid_vector_of_the_configured_size(): void
    {
        $service = app(EmbeddingService::class);
        $size = $service->dimensions();

        $vector = $this->safely(fn () => $service->embedQuery(self::QUERY));

        $this->assertGoodVector($vector, $size);
        $this->assertSame('gemini-embedding-001', $service->model(), 'The approved model is gemini-embedding-001.');
    }

    public function test_bengali_meaning_is_captured_and_batch_and_single_requests_agree(): void
    {
        $service = app(EmbeddingService::class);
        $size = $service->dimensions();

        $query = $this->safely(fn () => $service->embedQuery(self::QUERY));
        $batch = $this->safely(fn () => $service->embedDocuments([self::RELEVANT, self::UNRELATED]));
        $single = $this->safely(fn () => $service->embedDocument(self::RELEVANT));

        $this->assertCount(2, $batch);
        $this->assertGoodVector($batch[0], $size);
        $this->assertGoodVector($batch[1], $size);
        $this->assertGoodVector($single, $size);

        // The question about theft is closer to the sentence about the punishment for theft than to the weather report.
        $relevant = EmbeddingVector::cosineSimilarity($query, $batch[0]);
        $unrelated = EmbeddingVector::cosineSimilarity($query, $batch[1]);
        $this->assertGreaterThan($unrelated, $relevant, sprintf('Similarity to the matching sentence %.3f, to the unrelated one %.3f.', $relevant, $unrelated));

        // The batch endpoint and the single endpoint describe the same text the same way.
        $agreement = EmbeddingVector::cosineSimilarity($single, $batch[0]);
        $this->assertGreaterThan(0.99, $agreement, sprintf('Single and batch vectors agree only to %.4f.', $agreement));
    }
}
