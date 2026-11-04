<?php

namespace Tests\Unit\Services\AI\Embeddings;

use App\Services\AI\Embeddings\EmbeddingConnectionFailed;
use App\Services\AI\Embeddings\EmbeddingException;
use App\Services\AI\Embeddings\EmbeddingHttpError;
use App\Services\AI\Embeddings\EmbeddingInvalidResponse;
use App\Services\AI\Embeddings\EmbeddingMisconfigured;
use App\Services\AI\Embeddings\EmbeddingRateLimited;
use App\Services\AI\Embeddings\EmbeddingService;
use App\Services\AI\Embeddings\EmbeddingTaskType;
use App\Services\AI\Embeddings\EmbeddingTimedOut;
use App\Services\AI\Embeddings\EmbeddingVector;
use App\Services\AI\Embeddings\GoogleEmbeddingService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use SensitiveParameter;
use Tests\TestCase;
use Throwable;

/**
 * GoogleEmbeddingService against Laravel's HTTP fake. No test here can reach a real server: stray requests are prevented, so
 * an unfaked request fails the test instead of going out. The key is an obvious fake, and the real key (which the test
 * process may load from .env) is overwritten in setUp before anything runs.
 *
 * No assertion prints a whole vector: vectors are compared by size, by single numbers and by length.
 */
class GoogleEmbeddingServiceTest extends TestCase
{
    private const BASE = 'https://google.test/v1beta';

    private const KEY = 'test-key-not-real-0123456789';

    private const DIMS = 128;

    /** Stands for a private legal question. It must never show up in an exception or a log. */
    private const PRIVATE_TEXT = 'PRIVATE-CLIENT-MATTER-4711';

    /** Stands for text in Google's error body (which can quote the request). It must never be passed on. */
    private const BODY_MARKER = 'BODY-SECRET-MARKER';

    private const BENGALI_QUERY = 'চুরির শাস্তি কী?';

    private string|false $savedIgnoreArgs;

    private string|false $savedParamLength;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'google_ai.api_key' => self::KEY,
            'google_ai.base_url' => self::BASE,
            'google_ai.embedding_model' => 'gemini-embedding-001',
            'google_ai.embedding_dimensions' => self::DIMS,
            'google_ai.connect_timeout' => 3,
            'google_ai.timeout' => 20,
            'google_ai.batch_size' => 50,
        ]);

        Http::preventStrayRequests();

        // Make PHP put function arguments into stack traces, in full (PHP's own default cuts strings at 15 characters), so
        // the leak checks below look at the worst case.
        $this->savedIgnoreArgs = ini_set('zend.exception_ignore_args', '0');
        $this->savedParamLength = ini_set('zend.exception_string_param_max_len', '500');
    }

    protected function tearDown(): void
    {
        if ($this->savedIgnoreArgs !== false) {
            ini_set('zend.exception_ignore_args', $this->savedIgnoreArgs);
        }

        if ($this->savedParamLength !== false) {
            ini_set('zend.exception_string_param_max_len', $this->savedParamLength);
        }

        parent::tearDown();
    }

    // ---- helpers ---------------------------------------------------------------------------------------------

    /** @return list<float> a vector that is not of length 1, with positive and negative values */
    private function raw(int $size = self::DIMS, int $seed = 1): array
    {
        $values = [];

        for ($i = 0; $i < $size; $i++) {
            $values[] = round(sin($i * 0.37 + $seed) * 3.0 + 0.1, 6);
        }

        return $values;
    }

    /** @return array<string, mixed> what Google sends for one text */
    private function single(?array $values = null): array
    {
        return ['embedding' => ['values' => $values ?? $this->raw()]];
    }

    private function fakeSingle(?array $values = null): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->single($values))]);
    }

    /** Answers a batch with one vector per request: all zeros except a 2.0 at the position named by the text's last number. */
    private function fakeBatchByTextNumber(): void
    {
        Http::fake(function (Request $request) {
            $embeddings = [];

            foreach ($request->data()['requests'] as $item) {
                preg_match('/(\d+)$/', $item['content']['parts'][0]['text'], $match);
                $vector = array_fill(0, self::DIMS, 0.0);
                $vector[(int) $match[1]] = 2.0;
                $embeddings[] = ['values' => $vector];
            }

            return Http::response(['embeddings' => $embeddings]);
        });
    }

    private function service(): GoogleEmbeddingService
    {
        return new GoogleEmbeddingService;
    }

    /** @return list<Request> */
    private function sentRequests(): array
    {
        return array_map(fn (array $pair) => $pair[0], Http::recorded()->all());
    }

    private function onlyRequest(): Request
    {
        $requests = $this->sentRequests();
        $this->assertCount(1, $requests);

        return $requests[0];
    }

    private function assertVectorOk(array $vector, int $size = self::DIMS): void
    {
        $this->assertCount($size, $vector);
        $this->assertTrue(array_is_list($vector));
        $this->assertContainsOnlyFloat($vector);
        $this->assertTrue(EmbeddingVector::isUnitLength($vector, 1e-9), 'The vector is not of length 1.');
    }

    // ---- success ---------------------------------------------------------------------------------------------

    public function test_embed_query_returns_a_unit_vector_of_the_configured_size(): void
    {
        $this->fakeSingle();

        $this->assertVectorOk($this->service()->embedQuery(self::BENGALI_QUERY));
    }

    public function test_embed_document_returns_a_unit_vector_of_the_configured_size(): void
    {
        $this->fakeSingle();

        $this->assertVectorOk($this->service()->embedDocument('দণ্ডবিধির ৩৭৯ ধারা অনুযায়ী চুরির শাস্তি।'));
    }

    public function test_the_service_can_be_resolved_from_the_container_by_its_interface(): void
    {
        $this->fakeSingle();

        $service = app(EmbeddingService::class);

        $this->assertInstanceOf(GoogleEmbeddingService::class, $service);
        $this->assertVectorOk($service->embedQuery('x'));
    }

    public function test_extra_fields_in_the_answer_are_ignored(): void
    {
        Http::fake(['*' => Http::response($this->single() + ['usageMetadata' => ['promptTokenCount' => 7], 'future' => ['x' => 1]])]);

        $this->assertVectorOk($this->service()->embedQuery('x'));
    }

    // ---- what is sent ----------------------------------------------------------------------------------------

    public function test_a_single_text_is_posted_to_the_documented_embed_content_endpoint(): void
    {
        $this->fakeSingle();

        $this->service()->embedQuery('x');

        $request = $this->onlyRequest();
        $this->assertSame('POST', $request->method());
        $this->assertSame('https://google.test/v1beta/models/gemini-embedding-001:embedContent', $request->url());
    }

    public function test_the_default_google_address_is_the_documented_one(): void
    {
        $config = require base_path('config/google_ai.php');

        $this->assertStringContainsString('https://generativelanguage.googleapis.com/v1beta', file_get_contents(base_path('config/google_ai.php')));
        $this->assertIsArray($config);
    }

    public function test_the_request_body_has_exactly_the_documented_fields(): void
    {
        $this->fakeSingle();

        $this->service()->embedQuery('hello');

        $this->assertSame([
            'model' => 'models/gemini-embedding-001',
            'content' => ['parts' => [['text' => 'hello']]],
            'taskType' => 'RETRIEVAL_QUERY',
            'outputDimensionality' => self::DIMS,
        ], $this->onlyRequest()->data());
    }

    public function test_a_query_uses_retrieval_query_and_a_document_uses_retrieval_document(): void
    {
        $this->fakeSingle();

        $this->service()->embedQuery('q');
        $this->service()->embedDocument('d');

        [$query, $document] = $this->sentRequests();
        $this->assertSame('RETRIEVAL_QUERY', $query->data()['taskType']);
        $this->assertSame('RETRIEVAL_DOCUMENT', $document->data()['taskType']);
    }

    public function test_the_task_types_are_the_ones_google_documents(): void
    {
        $this->assertSame([
            'SEMANTIC_SIMILARITY', 'CLASSIFICATION', 'CLUSTERING', 'RETRIEVAL_DOCUMENT', 'RETRIEVAL_QUERY',
            'CODE_RETRIEVAL_QUERY', 'QUESTION_ANSWERING', 'FACT_VERIFICATION',
        ], array_map(fn (EmbeddingTaskType $type) => $type->value, EmbeddingTaskType::cases()));
    }

    /** @return array<string, array{string}> */
    public static function texts(): array
    {
        return [
            'a Bengali question' => ['চুরির শাস্তি কী?'],
            'Bengali with conjuncts and digits' => ['দণ্ডবিধির ৩৭৯ ধারা: চুরির শাস্তি তিন বছর পর্যন্ত কারাদণ্ড, বা অর্থদণ্ড, বা উভয়দণ্ড।'],
            'Bengali and English mixed' => ['Penal Code ধারা 379 এর অধীনে theft এর শাস্তি'],
            'a Bengali letter written as two code points' => ["\u{09A1}\u{09BC} and \u{09DC}"],
            'emoji and symbols' => ['⚖️ § 12 — “quoted” 🙂'],
            'line breaks and tabs' => ["line one\n\tline two\r\nline three"],
            'leading and trailing spaces are not trimmed' => ['  spaced  '],
            'quotes and backslashes' => ['He said "no" \\ and left'],
        ];
    }

    #[DataProvider('texts')]
    public function test_the_text_reaches_google_exactly_as_given(string $text): void
    {
        $this->fakeSingle();

        $this->service()->embedDocument($text);

        $request = $this->onlyRequest();
        $this->assertSame($text, $request->data()['content']['parts'][0]['text']);
        // And in the raw JSON that goes over the wire.
        $this->assertSame($text, json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR)['content']['parts'][0]['text']);
    }

    public function test_the_api_key_travels_only_in_the_documented_header(): void
    {
        $this->fakeSingle();

        $this->service()->embedQuery(self::PRIVATE_TEXT);

        $request = $this->onlyRequest();
        $this->assertSame([self::KEY], $request->header('x-goog-api-key'));
        $this->assertStringNotContainsString(self::KEY, $request->url());
        $this->assertStringNotContainsString(self::KEY, $request->body());
        $this->assertStringNotContainsString('?', $request->url(), 'There must be no query string.');
        $this->assertStringNotContainsString('key=', $request->url());
        $this->assertSame([], $request->header('Authorization'));
        $this->assertSame([], $request->header('Cookie'));
    }

    public function test_the_request_is_json_in_and_out(): void
    {
        $this->fakeSingle();

        $this->service()->embedQuery('x');

        $request = $this->onlyRequest();
        $this->assertStringContainsString('application/json', $request->header('Content-Type')[0]);
        $this->assertStringContainsString('application/json', $request->header('Accept')[0]);
    }

    public function test_the_configured_timeouts_are_used_and_redirects_are_off(): void
    {
        $options = null;
        Http::fake(function (Request $request, array $given) use (&$options) {
            $options = $given;

            return Http::response($this->single());
        });

        $this->service()->embedQuery('x');

        $this->assertEquals(3, $options['connect_timeout']);
        $this->assertEquals(20, $options['timeout']);
        $this->assertFalse($options['allow_redirects']);
    }

    public function test_a_trailing_slash_in_the_base_url_is_handled(): void
    {
        Http::fake(['*' => Http::response($this->single())]);
        config(['google_ai.base_url' => 'https://google.test/v1beta/']);

        $this->service()->embedQuery('x');

        $this->assertSame('https://google.test/v1beta/models/gemini-embedding-001:embedContent', $this->onlyRequest()->url());
    }

    public function test_the_configured_model_is_used_in_the_path_and_the_body(): void
    {
        Http::fake(['*' => Http::response($this->single())]);
        config(['google_ai.embedding_model' => 'gemini-embedding-2']);

        $this->service()->embedQuery('x');

        $request = $this->onlyRequest();
        $this->assertSame('https://google.test/v1beta/models/gemini-embedding-2:embedContent', $request->url());
        $this->assertSame('models/gemini-embedding-2', $request->data()['model']);
    }

    /** @return array<string, array{int}> */
    public static function dimensionSizes(): array
    {
        return ['128' => [128], '768' => [768], '1536 (the chosen size)' => [1536], '3072 (Google default)' => [3072]];
    }

    #[DataProvider('dimensionSizes')]
    public function test_the_configured_size_is_requested_and_checked_in_the_answer(int $size): void
    {
        config(['google_ai.embedding_dimensions' => $size]);
        $this->fakeSingle($this->raw($size));

        $vector = $this->service()->embedQuery('x');

        $this->assertSame($size, $this->onlyRequest()->data()['outputDimensionality']);
        $this->assertVectorOk($vector, $size);
    }

    // ---- normalization ---------------------------------------------------------------------------------------

    public function test_the_vector_is_scaled_to_length_one_keeping_its_direction(): void
    {
        $this->fakeSingle(array_merge([3, 4], array_fill(0, self::DIMS - 2, 0)));

        $vector = $this->service()->embedQuery('x');

        $this->assertEqualsWithDelta(0.6, $vector[0], 1e-12);
        $this->assertEqualsWithDelta(0.8, $vector[1], 1e-12);
        $this->assertSame(0.0, $vector[2]);
        $this->assertVectorOk($vector);
    }

    public function test_a_vector_that_already_has_length_one_is_not_changed_noticeably(): void
    {
        $unit = EmbeddingVector::normalized($this->raw());
        $this->fakeSingle($unit);

        $vector = $this->service()->embedQuery('x');

        foreach ($unit as $i => $value) {
            $this->assertEqualsWithDelta($value, $vector[$i], 1e-12);
        }
    }

    public function test_whole_numbers_in_the_answer_are_accepted_and_returned_as_floats(): void
    {
        $this->fakeSingle(array_merge([1, -2], array_fill(0, self::DIMS - 2, 0)));

        $this->assertVectorOk($this->service()->embedQuery('x'));
    }

    // ---- answers that are not usable -------------------------------------------------------------------------

    /** @param  array<string, mixed>  $change  position => replacement */
    private function bodyWithValues(array $change = []): string
    {
        $values = $this->raw();

        foreach ($change as $position => $value) {
            $values[$position] = $value;
        }

        return str_replace('"INF_MARKER"', '1e999', (string) json_encode(['embedding' => ['values' => $values]]));
    }

    /** @return array<string, array{string, string}> a way to damage the answer, and the reason expected */
    public static function damagedAnswers(): array
    {
        return [
            'one value too few' => ['too_few', 'dimension_mismatch'],
            'one value too many' => ['too_many', 'dimension_mismatch'],
            'no values' => ['no_values', 'dimension_mismatch'],
            'the 3072 size when 128 is configured' => ['google_default_size', 'dimension_mismatch'],
            'a number written as a string' => ['string_value', 'non_numeric_value'],
            'a null in the vector' => ['null_value', 'non_numeric_value'],
            'a boolean in the vector' => ['bool_value', 'non_numeric_value'],
            'a list inside the vector' => ['nested_value', 'non_numeric_value'],
            'infinity (1e999)' => ['infinity', 'non_finite_value'],
            'minus infinity' => ['minus_infinity', 'non_finite_value'],
            'all zeros' => ['all_zeros', 'zero_vector'],
            'an object instead of a list' => ['object_values', 'not_a_list'],
        ];
    }

    #[DataProvider('damagedAnswers')]
    public function test_a_damaged_vector_is_refused_with_a_reason_and_no_content(string $damage, string $reason): void
    {
        $values = $this->raw();
        $body = match ($damage) {
            'too_few' => $this->single(array_slice($values, 0, self::DIMS - 1)),
            'too_many' => $this->single([...$values, 0.5]),
            'no_values' => $this->single([]),
            'google_default_size' => $this->single($this->raw(3072)),
            'string_value' => $this->single([...array_slice($values, 0, 5), '0.5', ...array_slice($values, 6)]),
            'null_value' => $this->single([...array_slice($values, 0, 5), null, ...array_slice($values, 6)]),
            'bool_value' => $this->single([...array_slice($values, 0, 5), true, ...array_slice($values, 6)]),
            'nested_value' => $this->single([...array_slice($values, 0, 5), [0.5], ...array_slice($values, 6)]),
            'infinity' => $this->bodyWithValues([5 => 'INF_MARKER']),
            'minus_infinity' => str_replace('1e999', '-1e999', $this->bodyWithValues([5 => 'INF_MARKER'])),
            'all_zeros' => $this->single(array_fill(0, self::DIMS, 0)),
            'object_values' => $this->single(array_combine(array_map(fn ($i) => "k{$i}", array_keys($values)), $values)),
        };
        Http::fake(['*' => Http::response($body)]);

        try {
            $this->service()->embedQuery(self::PRIVATE_TEXT);
            $this->fail('A damaged vector was accepted.');
        } catch (EmbeddingInvalidResponse $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame('The embedding service returned an unusable response.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    /** @return array<string, array{string, string}> body, reason */
    public static function unusableBodies(): array
    {
        return [
            'an empty body' => ['', 'not_json'],
            'text, not JSON' => ['<html>Service Unavailable</html>', 'not_json'],
            'a JSON string' => ['"hello"', 'not_json'],
            'JSON null' => ['null', 'not_json'],
            'an empty list' => ['[]', 'missing_embedding'],
            'an empty object' => ['{}', 'missing_embedding'],
            'no embedding' => ['{"other":1}', 'missing_embedding'],
            'embedding is null' => ['{"embedding":null}', 'missing_embedding'],
            'embedding is a string' => ['{"embedding":"BODY-SECRET-MARKER"}', 'missing_embedding'],
            'embedding is an empty object' => ['{"embedding":{}}', 'missing_embedding'],
            'values is null' => ['{"embedding":{"values":null}}', 'missing_embedding'],
            'values is a string' => ['{"embedding":{"values":"BODY-SECRET-MARKER"}}', 'missing_embedding'],
            'the batch shape on the single endpoint' => ['{"embeddings":[{"values":[1.0]}]}', 'missing_embedding'],
            'an error object with status 200' => ['{"error":{"code":400,"message":"BODY-SECRET-MARKER"}}', 'missing_embedding'],
        ];
    }

    #[DataProvider('unusableBodies')]
    public function test_an_unusable_answer_is_refused(string $body, string $reason): void
    {
        Http::fake(['*' => Http::response($body, 200)]);

        try {
            $this->service()->embedQuery(self::PRIVATE_TEXT);
            $this->fail('An unusable answer was accepted.');
        } catch (EmbeddingInvalidResponse $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertNoLeak($e);
        }
    }

    public function test_a_204_with_no_content_is_refused(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        $this->expectException(EmbeddingInvalidResponse::class);

        $this->service()->embedQuery('x');
    }

    // ---- http errors -----------------------------------------------------------------------------------------

    /** @return array<string, array{int, string}> status, reason */
    public static function errorStatuses(): array
    {
        return [
            '400' => [400, 'http_error'], '401' => [401, 'api_key_rejected'], '403' => [403, 'access_denied'],
            '404' => [404, 'http_error'], '408' => [408, 'http_error'], '413' => [413, 'http_error'],
            '500' => [500, 'http_error'], '502' => [502, 'http_error'], '503' => [503, 'http_error'], '504' => [504, 'http_error'],
            '301' => [301, 'http_error'], '302' => [302, 'http_error'], '307' => [307, 'http_error'],
        ];
    }

    #[DataProvider('errorStatuses')]
    public function test_an_error_status_becomes_an_http_error_with_the_status(int $status, string $reason): void
    {
        Http::fake(['*' => Http::response('{"error":{"message":"'.self::BODY_MARKER.' '.self::PRIVATE_TEXT.'"}}', $status)]);

        try {
            $this->service()->embedQuery(self::PRIVATE_TEXT);
            $this->fail('expected');
        } catch (EmbeddingHttpError $e) {
            $this->assertSame($status, $e->status);
            $this->assertSame($reason, $e->reason);
            $this->assertSame('The embedding service returned an error.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    public function test_a_redirect_is_not_followed_so_the_key_can_not_be_sent_elsewhere(): void
    {
        Http::fake(['https://google.test/*' => Http::response('', 302, ['Location' => 'https://evil.test/collect'])]);

        try {
            $this->service()->embedQuery('x');
            $this->fail('expected');
        } catch (EmbeddingHttpError $e) {
            $this->assertSame(302, $e->status);
        }

        Http::assertSentCount(1); // and preventStrayRequests() would fail the test if evil.test had been contacted
    }

    public function test_google_says_the_key_is_invalid_with_a_400(): void
    {
        Http::fake(['*' => Http::response([
            'error' => [
                'code' => 400,
                'message' => 'API key not valid. '.self::BODY_MARKER,
                'status' => 'INVALID_ARGUMENT',
                'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'API_KEY_INVALID']],
            ],
        ], 400)]);

        try {
            $this->service()->embedQuery('x');
            $this->fail('expected');
        } catch (EmbeddingHttpError $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame('api_key_rejected', $e->reason);
            $this->assertSame('INVALID_ARGUMENT', $e->googleStatus);
            $this->assertNoLeak($e);
        }
    }

    /** @return array<string, array{mixed, ?string}> */
    public static function googleStatuses(): array
    {
        return [
            'PERMISSION_DENIED' => ['PERMISSION_DENIED', 'PERMISSION_DENIED'],
            'RESOURCE_EXHAUSTED' => ['RESOURCE_EXHAUSTED', 'RESOURCE_EXHAUSTED'],
            'UNAVAILABLE' => ['UNAVAILABLE', 'UNAVAILABLE'],
            'a code that is not on the list' => ['SOMETHING_NEW', null],
            'free text pretending to be a code' => ['BODY-SECRET-MARKER', null],
            'lower case' => ['invalid_argument', null],
            'a number' => [400, null],
            'a list' => [['INVALID_ARGUMENT'], null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('googleStatuses')]
    public function test_only_known_google_status_codes_are_copied(mixed $given, ?string $expected): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 500, 'message' => self::BODY_MARKER, 'status' => $given]], 500)]);

        try {
            $this->service()->embedQuery('x');
            $this->fail('expected');
        } catch (EmbeddingHttpError $e) {
            $this->assertSame($expected, $e->googleStatus);
            $this->assertNoLeak($e);
        }
    }

    public function test_an_error_body_that_is_not_json_or_has_odd_shapes_is_still_just_an_http_error(): void
    {
        $body = '';
        Http::fake(function () use (&$body) { // one stub that reads the current body (the first stub always wins)
            return Http::response($body, 500);
        });

        foreach (['<html>', '', '[]', '{"error":"BODY-SECRET-MARKER"}', '{"error":{"details":"BODY-SECRET-MARKER"}}', '{"error":{"details":[1,"x",null,{"reason":5}]}}'] as $body) {
            try {
                $this->service()->embedQuery('x');
                $this->fail('expected');
            } catch (EmbeddingHttpError $e) {
                $this->assertSame(500, $e->status);
                $this->assertSame('http_error', $e->reason);
                $this->assertNull($e->googleStatus);
            }
        }
    }

    public function test_a_429_is_a_rate_limit_not_a_plain_http_error(): void
    {
        Http::fake(['*' => Http::response('{"error":{"status":"RESOURCE_EXHAUSTED","message":"'.self::BODY_MARKER.'"}}', 429)]);

        try {
            $this->service()->embedQuery(self::PRIVATE_TEXT);
            $this->fail('expected');
        } catch (EmbeddingRateLimited $e) {
            $this->assertSame('rate_limited', $e->reason);
            $this->assertNull($e->retryAfterSeconds);
            $this->assertSame('The embedding service is busy. Try again later.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    /** @return array<string, array{?string, ?int}> */
    public static function retryAfterValues(): array
    {
        return [
            'absent' => [null, null], 'thirty seconds' => ['30', 30], 'zero' => ['0', 0], 'spaces around' => [' 12 ', 12],
            'one day' => ['86400', 86400], 'more than a day' => ['86401', null], 'a huge number' => ['99999999999', null],
            'negative' => ['-5', null], 'a decimal' => ['1.5', null], 'a word' => ['soon', null], 'empty' => ['', null],
            'an HTTP date' => ['Wed, 21 Oct 2026 07:28:00 GMT', null],
        ];
    }

    #[DataProvider('retryAfterValues')]
    public function test_the_retry_after_hint_is_passed_on_only_when_it_is_sensible(?string $header, ?int $expected): void
    {
        Http::fake(['*' => Http::response('', 429, $header === null ? [] : ['Retry-After' => $header])]);

        try {
            $this->service()->embedQuery('x');
            $this->fail('expected');
        } catch (EmbeddingRateLimited $e) {
            $this->assertSame($expected, $e->retryAfterSeconds);
        }
    }

    public function test_a_failure_is_not_retried(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        try {
            $this->service()->embedQuery('x');
        } catch (EmbeddingHttpError) {
        }

        Http::assertSentCount(1);
    }

    // ---- connection failures and timeouts --------------------------------------------------------------------

    public function test_a_connection_failure_is_reported_safely(): void
    {
        Http::fake(Http::failedConnection('cURL error 7: Failed to connect to google.test port 443 after 2 ms: Couldn\'t connect to server'));

        try {
            $this->service()->embedQuery(self::PRIVATE_TEXT);
            $this->fail('expected');
        } catch (EmbeddingConnectionFailed $e) {
            $this->assertSame('connection_failed', $e->reason);
            $this->assertSame('The embedding service could not be reached.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    public function test_an_unresolvable_host_is_a_connection_failure(): void
    {
        Http::fake(Http::failedConnection());

        $this->expectException(EmbeddingConnectionFailed::class);

        $this->service()->embedQuery('x');
    }

    /** @return array<string, array{class-string<TransferException>}> */
    public static function timeoutExceptions(): array
    {
        return [
            'no answer in time' => [NetworkTimeoutException::class],
            'connection did not open in time' => [ConnectTimeoutException::class],
        ];
    }

    #[DataProvider('timeoutExceptions')]
    public function test_a_timeout_is_told_apart_from_a_connection_failure(string $exception): void
    {
        Http::fake(fn (Request $request) => Create::rejectionFor(new $exception(
            'cURL error 28: Operation timed out after 20001 milliseconds with 0 bytes received for https://google.test/v1beta/models/gemini-embedding-001:embedContent',
            $request->toPsrRequest(),
        )));

        try {
            $this->service()->embedQuery(self::PRIVATE_TEXT);
            $this->fail('expected');
        } catch (EmbeddingTimedOut $e) {
            $this->assertSame('timed_out', $e->reason);
            $this->assertSame('The embedding service did not answer in time.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    public function test_a_plain_connection_refusal_is_not_taken_for_a_timeout(): void
    {
        Http::fake(fn (Request $request) => Create::rejectionFor(new ConnectException(
            'cURL error 7: Failed to connect to google.test port 443 after 1 ms: Could not connect to server',
            $request->toPsrRequest(),
        )));

        $this->expectException(EmbeddingConnectionFailed::class);

        $this->service()->embedQuery('x');
    }

    public function test_a_read_that_times_out_after_the_headers_arrived_is_still_a_timeout(): void
    {
        Http::fake(fn (Request $request) => Create::rejectionFor(new ResponseTimeoutException(
            'cURL error 28: Operation timed out after 20001 milliseconds with 12 bytes received',
            $request->toPsrRequest(),
            new Psr7Response(200, ['Content-Type' => 'application/json'], '{"embedding":{"val'),
        )));

        $this->expectException(EmbeddingTimedOut::class);

        $this->service()->embedQuery('x');
    }

    public function test_a_timeout_is_recognised_from_the_message_when_there_is_no_error_context(): void
    {
        Http::fake(Http::failedConnection('cURL error 28: Connection timed out after 3001 milliseconds'));

        $this->expectException(EmbeddingTimedOut::class);

        $this->service()->embedQuery('x');
    }

    public function test_other_curl_errors_that_merely_contain_28_are_not_timeouts(): void
    {
        Http::fake(Http::failedConnection('cURL error 7: Failed to connect to google.test port 4428 after 28 ms'));

        $this->expectException(EmbeddingConnectionFailed::class);

        $this->service()->embedQuery('x');
    }

    public function test_the_low_level_exception_is_not_chained(): void
    {
        Http::fake(Http::failedConnection('cURL error 7: Failed to connect to google.test'));

        try {
            $this->service()->embedQuery('x');
            $this->fail('expected');
        } catch (EmbeddingConnectionFailed $e) {
            $this->assertNull($e->getPrevious()); // its message would carry the request URL
        }
    }

    // ---- the key and the text must never leak ----------------------------------------------------------------

    /**
     * What an exception shows to whoever reads a log: its message, and the arguments PHP recorded for every stack frame that
     * belongs to the application, written the way PHP writes a trace (a string in quotes, an object as Object(Class), an
     * array as Array). Frames of PHPUnit and of the tests are left out on purpose: PHPUnit puts the data-set values of a test
     * into its own frame, which has nothing to do with what the service exposes.
     */
    private function visibleIn(Throwable $e): string
    {
        return $e->getMessage()."\n".$e->getFile().$this->describeFrames($e->getTrace());
    }

    /** @param  list<array<string, mixed>>  $frames */
    private function describeFrames(array $frames): string
    {
        $shown = '';

        foreach ($frames as $frame) {
            if (! str_starts_with($frame['class'] ?? '', 'App\\')) {
                continue;
            }

            $arguments = array_map(fn ($argument) => match (true) {
                is_string($argument) => "'{$argument}'",
                is_object($argument) => 'Object('.get_class($argument).')',
                is_array($argument) => 'Array',
                default => var_export($argument, true),
            }, $frame['args'] ?? []);

            $shown .= "\n".$frame['class'].'::'.$frame['function'].'('.implode(', ', $arguments).')';
        }

        return $shown;
    }

    private function assertNoLeak(Throwable $e): void
    {
        $everything = $this->visibleIn($e);

        foreach ([self::KEY, self::PRIVATE_TEXT, self::BODY_MARKER, 'google.test', 'x-goog-api-key'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything, 'An exception shows something it must not.');
        }

        $this->assertNull($e->getPrevious());
    }

    public function test_the_leak_check_can_fail(): void
    {
        // Proof that the check looks at real stack-trace arguments: a frame of the application that carries a plain string
        // would be shown in full, while the frames of the service itself (whose text parameters are hidden) show nothing.
        $frames = [
            ['class' => 'App\\Services\\Fake', 'function' => 'leaky', 'args' => [self::PRIVATE_TEXT, ['x'], new \stdClass, 5]],
            ['class' => 'PHPUnit\\Framework\\TestCase', 'function' => 'runTest', 'args' => ['not shown, not ours']],
        ];

        $shown = $this->describeFrames($frames);

        $this->assertStringContainsString("App\\Services\\Fake::leaky('".self::PRIVATE_TEXT."', Array, Object(stdClass), 5)", $shown);
        $this->assertStringNotContainsString('not shown', $shown);
    }

    /**
     * Http::fake() keeps the FIRST stub that matches, so tests that need several behaviours register one stub that asks
     * this property what to do now.
     *
     * @var callable(Request): mixed
     */
    private $nextStub;

    private function fakeWhatever(): void
    {
        Http::fake(fn (Request $request) => ($this->nextStub)($request));
    }

    private function anyRequest(): Request
    {
        return new Request(new \GuzzleHttp\Psr7\Request('POST', self::BASE.'/models/gemini-embedding-001:embedContent'));
    }

    /** @return array<string, array{0: class-string<EmbeddingException>|null, 1: callable}> */
    private function scenarios(): array
    {
        return [
            'answer' => [null, fn () => Http::response($this->single())],
            'connection' => [EmbeddingConnectionFailed::class, fn () => Http::failedConnection('cURL error 7: x '.self::KEY)($this->anyRequest())],
            'timeout' => [EmbeddingTimedOut::class, fn () => Http::failedConnection('cURL error 28: x')($this->anyRequest())],
            'rate limit' => [EmbeddingRateLimited::class, fn () => Http::response(self::BODY_MARKER, 429)],
            'server error' => [EmbeddingHttpError::class, fn () => Http::response(self::BODY_MARKER, 500)],
            'key rejected' => [EmbeddingHttpError::class, fn () => Http::response(self::BODY_MARKER, 401)],
            'not json' => [EmbeddingInvalidResponse::class, fn () => Http::response(self::BODY_MARKER, 200)],
            'wrong size' => [EmbeddingInvalidResponse::class, fn () => Http::response($this->single([1.0, 2.0]))],
        ];
    }

    public function test_no_failure_of_any_kind_shows_the_key_or_the_text_in_any_way(): void
    {
        $this->fakeWhatever();

        foreach ($this->scenarios() as $name => [$expected, $stub]) {
            $this->nextStub = $stub;

            foreach (['embedQuery', 'embedDocument'] as $method) {
                try {
                    $this->service()->{$method}(self::PRIVATE_TEXT);
                    $this->assertNull($expected, "{$name}: expected a failure");
                } catch (EmbeddingException $e) {
                    $this->assertSame($expected, $e::class, $name);
                    $this->assertNoLeak($e);
                }
            }

            try {
                $this->service()->embedDocuments([self::PRIVATE_TEXT]);
            } catch (EmbeddingException $e) {
                // A single-vector answer is not a batch answer, so even the "answer" scenario ends here as an invalid response.
                $this->assertNoLeak($e);
            }
        }
    }

    public function test_the_service_never_logs_on_success_or_on_failure(): void
    {
        Log::spy();
        $this->fakeWhatever();
        $outcomes = [];

        foreach ($this->scenarios() as $name => [$expected, $stub]) {
            $this->nextStub = $stub;

            try {
                $this->service()->embedQuery(self::PRIVATE_TEXT);
                $outcomes[$name] = 'answer';
            } catch (EmbeddingException $e) {
                $outcomes[$name] = $e::class;
            }
        }

        // Prove every path really ran, so "nothing was logged" is not true just because nothing happened.
        $this->assertSame(array_map(fn (array $s) => $s[0] ?? 'answer', $this->scenarios()), $outcomes);

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_the_text_parameters_are_marked_sensitive_so_php_hides_them_in_traces(): void
    {
        foreach (['embedDocument', 'embedQuery'] as $method) {
            $parameter = (new ReflectionMethod(GoogleEmbeddingService::class, $method))->getParameters()[0];

            $this->assertNotEmpty($parameter->getAttributes(SensitiveParameter::class), "{$method} must hide its text");
        }

        foreach (['embedOne', 'checkText', 'requestFor'] as $method) {
            $parameter = (new ReflectionMethod(GoogleEmbeddingService::class, $method))->getParameters()[0];

            $this->assertNotEmpty($parameter->getAttributes(SensitiveParameter::class), "{$method} must hide its text");
        }
    }

    public function test_the_class_has_no_logging_and_no_dumping_calls_at_all(): void
    {
        $source = (string) file_get_contents(app_path('Services/AI/Embeddings/GoogleEmbeddingService.php'));
        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source) ?? '';

        $this->assertDoesNotMatchRegularExpression('/\b(Log::|logger\(|report\(|dump\(|dd\(|var_dump\(|print_r\(|error_log\(|echo\s|print\s)/', $code);
    }

    // ---- misconfiguration: nothing may be sent ---------------------------------------------------------------

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function brokenConfigurations(): array
    {
        return [
            'no key' => [['google_ai.api_key' => null], 'missing_api_key'],
            'blank key' => [['google_ai.api_key' => '  '], 'missing_api_key'],
            'a key with a line break' => [['google_ai.api_key' => "test-key-not-real\nx-evil: 1"], 'invalid_api_key'],
            'plain http to a real host' => [['google_ai.base_url' => 'http://generativelanguage.googleapis.com/v1beta'], 'invalid_base_url'],
            'no base url' => [['google_ai.base_url' => ''], 'invalid_base_url'],
            'a model that could change the method' => [['google_ai.embedding_model' => 'gemini-embedding-001:batchEmbedContents'], 'invalid_model'],
            'no model' => [['google_ai.embedding_model' => null], 'invalid_model'],
            'size too small' => [['google_ai.embedding_dimensions' => 64], 'invalid_dimensions'],
            'size too large' => [['google_ai.embedding_dimensions' => 4096], 'invalid_dimensions'],
            'size not a number' => [['google_ai.embedding_dimensions' => 'big'], 'invalid_dimensions'],
        ];
    }

    #[DataProvider('brokenConfigurations')]
    public function test_a_broken_configuration_is_refused_before_anything_is_sent(array $settings, string $reason): void
    {
        Http::fake();
        config($settings);

        foreach ([
            fn () => $this->service()->embedQuery(self::PRIVATE_TEXT),
            fn () => $this->service()->embedDocument(self::PRIVATE_TEXT),
            fn () => $this->service()->embedDocuments([self::PRIVATE_TEXT]),
        ] as $call) {
            try {
                $call();
                $this->fail('A broken configuration was accepted.');
            } catch (EmbeddingMisconfigured $e) {
                $this->assertSame($reason, $e->reason);
                $this->assertSame('The embedding service is not configured correctly.', $e->getMessage());
                $this->assertNoLeak($e);
            }
        }

        Http::assertNothingSent();
    }

    public function test_model_and_dimensions_report_the_configuration(): void
    {
        config(['google_ai.embedding_model' => 'gemini-embedding-001', 'google_ai.embedding_dimensions' => '1536']);

        $this->assertSame('gemini-embedding-001', $this->service()->model());
        $this->assertSame(1536, $this->service()->dimensions());

        config(['google_ai.embedding_dimensions' => 'big']);
        $this->expectException(EmbeddingMisconfigured::class);
        $this->service()->dimensions();
    }

    // ---- unusable input: nothing may be sent -----------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function unusableTexts(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['    '],
            'line breaks and tabs' => ["\n\t \r\n"],
            'invalid UTF-8' => ["abc\xC3\x28def"],
            'a lone continuation byte' => ["\x80"],
            'more than the size limit' => [str_repeat('a', GoogleEmbeddingService::MAX_TEXT_BYTES + 1)],
        ];
    }

    #[DataProvider('unusableTexts')]
    public function test_an_unusable_text_is_refused_and_nothing_is_sent(string $text): void
    {
        Http::fake();

        foreach (['embedQuery', 'embedDocument'] as $method) {
            try {
                $this->service()->{$method}($text);
                $this->fail('An unusable text was accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringNotContainsString('aaaa', $e->getMessage());
                $this->assertStringNotContainsString('abc', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_a_text_of_exactly_the_size_limit_is_accepted(): void
    {
        $this->fakeSingle();

        $this->assertVectorOk($this->service()->embedDocument(str_repeat('a', GoogleEmbeddingService::MAX_TEXT_BYTES)));
    }

    public function test_the_size_limit_counts_bytes_so_bengali_letters_count_three(): void
    {
        $this->fakeSingle();

        $fits = str_repeat('ক', intdiv(GoogleEmbeddingService::MAX_TEXT_BYTES, 3));
        $this->assertVectorOk($this->service()->embedDocument($fits));

        $this->expectException(InvalidArgumentException::class);
        $this->service()->embedDocument($fits.'ক');
    }

    /** @return array<string, array{array<mixed>}> */
    public static function unusableTextLists(): array
    {
        return [
            'names instead of a list' => [['a' => 'x', 'b' => 'y']],
            'keys not starting at 0' => [[1 => 'x', 2 => 'y']],
            'a number in it' => [['x', 5]],
            'a null in it' => [['x', null]],
            'a nested list in it' => [['x', ['y']]],
            'a blank text in it' => [['x', '   ']],
            'invalid UTF-8 in it' => [['x', "\xC3\x28"]],
            'an oversized text in it' => [['x', str_repeat('a', GoogleEmbeddingService::MAX_TEXT_BYTES + 1)]],
        ];
    }

    #[DataProvider('unusableTextLists')]
    public function test_an_unusable_list_is_refused_as_a_whole_and_nothing_is_sent(array $texts): void
    {
        Http::fake();

        try {
            $this->service()->embedDocuments($texts);
            $this->fail('An unusable list was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        Http::assertNothingSent();
    }

    // ---- batches ---------------------------------------------------------------------------------------------

    public function test_an_empty_list_gives_an_empty_result_and_sends_nothing(): void
    {
        Http::fake();

        $this->assertSame([], $this->service()->embedDocuments([]));
        Http::assertNothingSent();
    }

    public function test_a_batch_is_posted_to_the_documented_batch_endpoint_with_one_request_per_text(): void
    {
        $this->fakeBatchByTextNumber();

        $this->service()->embedDocuments(['text-0', 'text-1', 'চুরি-2']);

        $request = $this->onlyRequest();
        $this->assertSame('POST', $request->method());
        $this->assertSame('https://google.test/v1beta/models/gemini-embedding-001:batchEmbedContents', $request->url());
        $this->assertSame([self::KEY], $request->header('x-goog-api-key'));
        $this->assertStringNotContainsString(self::KEY, $request->body());
        $this->assertSame(['requests'], array_keys($request->data()));
        $this->assertCount(3, $request->data()['requests']);

        foreach ($request->data()['requests'] as $item) {
            $this->assertSame(['model', 'content', 'taskType', 'outputDimensionality'], array_keys($item));
            $this->assertSame('models/gemini-embedding-001', $item['model']);
            $this->assertSame('RETRIEVAL_DOCUMENT', $item['taskType']);
            $this->assertSame(self::DIMS, $item['outputDimensionality']);
        }

        $this->assertSame('চুরি-2', $request->data()['requests'][2]['content']['parts'][0]['text']);
    }

    public function test_the_batch_result_has_one_unit_vector_per_text_in_the_same_order(): void
    {
        $this->fakeBatchByTextNumber();

        $vectors = $this->service()->embedDocuments(['text-3', 'text-0', 'text-7']);

        $this->assertCount(3, $vectors);

        foreach ([3, 0, 7] as $row => $hot) {
            $this->assertVectorOk($vectors[$row]);
            $this->assertEqualsWithDelta(1.0, $vectors[$row][$hot], 1e-12); // the 2.0 became 1.0
        }
    }

    public function test_a_long_list_is_split_into_batches_of_the_configured_size_and_keeps_its_order(): void
    {
        config(['google_ai.batch_size' => 2]);
        $this->fakeBatchByTextNumber();

        $vectors = $this->service()->embedDocuments(['text-0', 'text-1', 'text-2', 'text-3', 'text-4']);

        $requests = $this->sentRequests();
        $this->assertSame([2, 2, 1], array_map(fn (Request $r) => count($r->data()['requests']), $requests));
        $this->assertCount(5, $vectors);

        foreach ($vectors as $position => $vector) {
            $this->assertEqualsWithDelta(1.0, $vector[$position], 1e-12);
        }
    }

    public function test_the_default_batch_size_keeps_fifty_texts_in_one_request(): void
    {
        $this->fakeBatchByTextNumber();

        $this->service()->embedDocuments(array_map(fn (int $i) => "text-{$i}", range(0, 49)));

        Http::assertSentCount(1);
    }

    public function test_an_answer_with_the_wrong_number_of_vectors_is_refused(): void
    {
        Http::fake(['*' => Http::response(['embeddings' => [['values' => $this->raw()]]])]);

        try {
            $this->service()->embedDocuments(['a', 'b']);
            $this->fail('expected');
        } catch (EmbeddingInvalidResponse $e) {
            $this->assertSame('count_mismatch', $e->reason);
        }
    }

    /** @return array<string, array{mixed, string}> */
    public static function badBatchAnswers(): array
    {
        return [
            'no embeddings key' => [['other' => 1], 'missing_embedding'],
            'embeddings is not a list' => [['embeddings' => ['a' => ['values' => []]]], 'missing_embedding'],
            'embeddings is a string' => [['embeddings' => 'x'], 'missing_embedding'],
            'the single-answer shape' => [['embedding' => ['values' => []]], 'missing_embedding'],
            'an item that is not an object' => [['embeddings' => ['x']], 'missing_embedding'],
            'an item with no values' => [['embeddings' => [['other' => 1]]], 'missing_embedding'],
        ];
    }

    #[DataProvider('badBatchAnswers')]
    public function test_a_batch_answer_of_the_wrong_shape_is_refused(array $body, string $reason): void
    {
        Http::fake(['*' => Http::response($body)]);

        try {
            $this->service()->embedDocuments(['a']);
            $this->fail('expected');
        } catch (EmbeddingInvalidResponse $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_one_bad_vector_in_a_batch_refuses_the_whole_batch(): void
    {
        $short = array_slice($this->raw(), 0, 10);
        Http::fake(['*' => Http::response(['embeddings' => [['values' => $this->raw()], ['values' => $short]]])]);

        try {
            $this->service()->embedDocuments(['a', 'b']);
            $this->fail('expected');
        } catch (EmbeddingInvalidResponse $e) {
            $this->assertSame('dimension_mismatch', $e->reason);
        }
    }

    public function test_when_a_later_batch_fails_the_exception_is_thrown_and_nothing_partial_is_returned(): void
    {
        config(['google_ai.batch_size' => 1]);
        $calls = 0;
        Http::fake(function (Request $request) use (&$calls) {
            $calls++;

            return $calls === 2
                ? Http::response('', 503)
                : Http::response(['embeddings' => [['values' => $this->raw()]]]);
        });

        try {
            $result = $this->service()->embedDocuments(['a', 'b', 'c']);
            $this->fail('expected');
        } catch (EmbeddingHttpError $e) {
            $this->assertSame(503, $e->status);
        }

        $this->assertSame(2, $calls); // it stopped at the failure; the third batch was not attempted
        $this->assertFalse(isset($result));
    }

    public function test_a_batch_failure_does_not_show_the_key_or_any_text(): void
    {
        Http::fake(['*' => Http::response(self::BODY_MARKER, 500)]);

        try {
            $this->service()->embedDocuments([self::PRIVATE_TEXT, 'second '.self::PRIVATE_TEXT]);
            $this->fail('expected');
        } catch (EmbeddingException $e) {
            $this->assertNoLeak($e);
        }
    }

    // ---- no hidden state -------------------------------------------------------------------------------------

    public function test_calls_are_independent_and_follow_the_current_configuration(): void
    {
        $service = $this->service();
        $answerSize = 128;
        Http::fake(function () use (&$answerSize) { // one stub that reads the current size
            return Http::response($this->single($this->raw($answerSize)));
        });

        config(['google_ai.embedding_dimensions' => 128]);
        $this->assertCount(128, $service->embedQuery('x'));

        config(['google_ai.embedding_dimensions' => 256]);
        $answerSize = 256;
        $this->assertCount(256, $service->embedQuery('x'));

        // The 128-number answer no longer fits the 256 setting.
        $answerSize = 128;
        $this->expectException(EmbeddingInvalidResponse::class);
        $service->embedQuery('x');
    }

    public function test_the_service_holds_no_properties_so_nothing_can_be_kept_between_calls(): void
    {
        $this->assertSame([], (new \ReflectionClass(GoogleEmbeddingService::class))->getProperties());
    }
}
