<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\OllamaConnectionFailed;
use App\Services\AI\OllamaException;
use App\Services\AI\OllamaHttpError;
use App\Services\AI\OllamaInvalidResponse;
use App\Services\AI\OllamaMisconfigured;
use App\Services\AI\OllamaService;
use App\Services\AI\OllamaTimedOut;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * OllamaService against Laravel's HTTP fake. No test here can reach a real server: stray requests are prevented, so an
 * unfaked request fails the test instead of going out.
 */
class OllamaServiceTest extends TestCase
{
    private const BASE = 'http://ollama.test:11434';

    /** Text that stands for a private legal question. It must never show up in an exception or a log. */
    private const PRIVATE_TEXT = 'PRIVATE-CLIENT-MATTER-4711';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ollama.base_url' => self::BASE,
            'ollama.model' => 'test-model:1b',
            'ollama.connect_timeout' => 3,
            'ollama.timeout' => 30,
        ]);

        Http::preventStrayRequests();
    }

    /** @return array<string, mixed> what Ollama sends for a finished, non-streaming chat */
    private function answer(string $content = 'Hello from the model.'): array
    {
        return [
            'model' => 'test-model:1b',
            'created_at' => '2026-11-02T10:00:00.000000Z',
            'message' => ['role' => 'assistant', 'content' => $content],
            'done' => true,
            'done_reason' => 'stop',
        ];
    }

    private function fakeAnswer(string $content = 'Hello from the model.'): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->answer($content))]);
    }

    /** @return list<array{role: string, content: string}> */
    private function question(string $text = 'What is a power of attorney?'): array
    {
        return [['role' => 'user', 'content' => $text]];
    }

    private function service(): OllamaService
    {
        return new OllamaService;
    }

    // ---- success ---------------------------------------------------------------------------------------------

    public function test_it_returns_the_assistant_message_content(): void
    {
        $this->fakeAnswer('A power of attorney lets someone act for you.');

        $this->assertSame('A power of attorney lets someone act for you.', $this->service()->chat($this->question()));
        Http::assertSentCount(1);
    }

    public function test_the_content_is_returned_exactly_as_the_model_wrote_it(): void
    {
        $content = "  Line one\n\n  Line two  \n";
        $this->fakeAnswer($content);

        $this->assertSame($content, $this->service()->chat($this->question()));
    }

    public function test_the_service_can_be_resolved_from_the_container(): void
    {
        $this->fakeAnswer('ok');

        $this->assertSame('ok', app(OllamaService::class)->chat($this->question()));
    }

    // ---- what is sent ----------------------------------------------------------------------------------------

    public function test_it_posts_to_the_chat_endpoint_of_the_configured_server(): void
    {
        $this->fakeAnswer();

        $this->service()->chat($this->question());

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'http://ollama.test:11434/api/chat');
    }

    public function test_a_trailing_slash_or_a_path_prefix_in_the_base_url_is_handled(): void
    {
        Http::fake(['*' => Http::response($this->answer())]);

        config(['ollama.base_url' => 'http://ollama.test:11434/']);
        $this->service()->chat($this->question());

        config(['ollama.base_url' => 'https://proxy.test/ollama/']);
        $this->service()->chat($this->question());

        config(['ollama.base_url' => 'http://[::1]:11434']);
        $this->service()->chat($this->question());

        $urls = array_map(fn (array $pair) => $pair[0]->url(), Http::recorded()->all());
        $this->assertSame([
            'http://ollama.test:11434/api/chat',
            'https://proxy.test/ollama/api/chat',
            'http://[::1]:11434/api/chat',
        ], $urls);
    }

    public function test_the_model_comes_from_the_configuration(): void
    {
        $this->fakeAnswer();

        $this->service()->chat($this->question());
        Http::assertSent(fn (Request $request) => $request->data()['model'] === 'test-model:1b');

        config(['ollama.model' => 'gemma4:31b-cloud']); // read again on every call
        $this->service()->chat($this->question());
        Http::assertSent(fn (Request $request) => $request->data()['model'] === 'gemma4:31b-cloud');
    }

    public function test_the_default_model_is_the_one_in_the_shipped_configuration(): void
    {
        $config = require base_path('config/ollama.php');

        $this->assertSame('gemma4:31b-cloud', $config['model']);
    }

    public function test_the_messages_are_forwarded_in_order_and_unchanged(): void
    {
        $this->fakeAnswer();
        $conversation = [
            ['role' => 'system', 'content' => 'You are a careful legal assistant.'],
            ['role' => 'user', 'content' => 'What is a power of attorney?'],
            ['role' => 'assistant', 'content' => 'It lets someone act on your behalf.'],
            ['role' => 'user', 'content' => "Can it be revoked?\nAnd how?"],
        ];

        $this->service()->chat($conversation);

        Http::assertSent(fn (Request $request) => $request->data()['messages'] === $conversation);
    }

    public function test_only_role_and_content_are_forwarded(): void
    {
        $this->fakeAnswer();

        $this->service()->chat([[
            'role' => 'user',
            'content' => 'Hello',
            'images' => ['AAAA'],
            'tool_calls' => [['function' => ['name' => 'x']]],
            'something_else' => true,
        ]]);

        Http::assertSent(fn (Request $request) => $request->data()['messages'] === [['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_streaming_is_disabled(): void
    {
        $this->fakeAnswer();

        $this->service()->chat($this->question());

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return array_key_exists('stream', $data) && $data['stream'] === false;
        });
    }

    public function test_the_body_has_exactly_model_messages_and_stream(): void
    {
        $this->fakeAnswer();

        $this->service()->chat($this->question());

        Http::assertSent(function (Request $request) {
            $keys = array_keys($request->data());
            sort($keys);

            return $keys === ['messages', 'model', 'stream'];
        });
    }

    public function test_it_sends_and_accepts_json(): void
    {
        $this->fakeAnswer();

        $this->service()->chat($this->question());

        Http::assertSent(fn (Request $request) => $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/json'));
    }

    public function test_no_credentials_or_cookies_are_sent(): void
    {
        $this->fakeAnswer();

        $this->service()->chat($this->question());

        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization') && ! $request->hasHeader('Cookie'));
    }

    public function test_the_configured_timeouts_are_applied_and_redirects_are_not_followed(): void
    {
        $options = null;
        Http::fake(function (Request $request, array $given) use (&$options) {
            $options = $given;

            return Http::response($this->answer());
        });

        $this->service()->chat($this->question());

        $this->assertEquals(3, $options['connect_timeout']);
        $this->assertEquals(30, $options['timeout']);
        $this->assertFalse($options['allow_redirects']);
    }

    public function test_numeric_strings_from_the_environment_are_accepted_as_timeouts(): void
    {
        config(['ollama.connect_timeout' => '7', 'ollama.timeout' => '90.5']);
        $options = null;
        Http::fake(function (Request $request, array $given) use (&$options) {
            $options = $given;

            return Http::response($this->answer());
        });

        $this->service()->chat($this->question());

        $this->assertEquals(7, $options['connect_timeout']);
        $this->assertEquals(90.5, $options['timeout']);
    }

    public function test_a_bad_timeout_falls_back_to_the_default_instead_of_waiting_forever(): void
    {
        $seen = [];
        Http::fake(function (Request $request, array $given) use (&$seen) {
            $seen[] = [$given['connect_timeout'], $given['timeout']];

            return Http::response($this->answer());
        });

        foreach ([0, -5, '0', 'abc', '', null, false, [], 3601, '1e999', INF, NAN] as $bad) {
            config(['ollama.connect_timeout' => $bad, 'ollama.timeout' => $bad]);
            $this->service()->chat($this->question());
        }

        foreach ($seen as [$connect, $total]) {
            $this->assertEquals(5, $connect);
            $this->assertEquals(120, $total);
        }
        $this->assertCount(12, $seen);
    }

    // ---- errors from the server ------------------------------------------------------------------------------

    /** @return array<string, array{0: int}> */
    public static function errorStatuses(): array
    {
        return [
            '400' => [400], '401' => [401], '403' => [403], '404 model not found' => [404], '408' => [408],
            '429' => [429], '500' => [500], '502' => [502], '503' => [503], '504' => [504],
            '302 redirect is not followed' => [302], '301' => [301], '307' => [307],
        ];
    }

    #[DataProvider('errorStatuses')]
    public function test_a_non_success_status_is_an_http_error_carrying_the_status(int $status): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['error' => 'model "test-model:1b" not found', 'echo' => self::PRIVATE_TEXT], $status, ['Location' => 'http://elsewhere.test/'])]);

        try {
            $this->service()->chat($this->question(self::PRIVATE_TEXT));
            $this->fail('An OllamaHttpError was expected.');
        } catch (OllamaHttpError $e) {
            $this->assertSame($status, $e->status);
            $this->assertSame('http_error', $e->reason);
            $this->assertSame('The AI service returned an error.', $e->getMessage());
        }

        Http::assertSentCount(1); // a redirect is never followed
    }

    public function test_an_http_error_never_contains_the_response_body_the_prompt_or_the_address(): void
    {
        Http::fake([self::BASE.'/*' => Http::response('{"error":"leak '.self::PRIVATE_TEXT.' ollama.test"}', 500)]);

        try {
            $this->service()->chat($this->question(self::PRIVATE_TEXT));
            $this->fail('expected');
        } catch (OllamaHttpError $e) {
            $this->assertNoLeak($e);
        }
    }

    // ---- connection failures and timeouts --------------------------------------------------------------------

    public function test_a_connection_failure_is_reported_safely(): void
    {
        Http::fake(Http::failedConnection('cURL error 7: Failed to connect to ollama.test port 11434 after 2 ms: Couldn\'t connect to server'));

        try {
            $this->service()->chat($this->question(self::PRIVATE_TEXT));
            $this->fail('expected');
        } catch (OllamaConnectionFailed $e) {
            $this->assertSame('connection_failed', $e->reason);
            $this->assertSame('The AI service could not be reached.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    public function test_an_unresolvable_host_is_a_connection_failure(): void
    {
        Http::fake(Http::failedConnection()); // the framework's own "could not resolve host" simulation

        $this->expectException(OllamaConnectionFailed::class);

        $this->service()->chat($this->question());
    }

    /** @return array<string, array{0: class-string<\GuzzleHttp\Exception\TransferException>}> */
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
            'cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received for http://ollama.test:11434/api/chat',
            $request->toPsrRequest(),
        )));

        try {
            $this->service()->chat($this->question(self::PRIVATE_TEXT));
            $this->fail('expected');
        } catch (OllamaTimedOut $e) {
            $this->assertSame('timed_out', $e->reason);
            $this->assertSame('The AI service did not answer in time.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    public function test_a_plain_connection_refusal_is_not_taken_for_a_timeout(): void
    {
        Http::fake(fn (Request $request) => Create::rejectionFor(new ConnectException(
            'cURL error 7: Failed to connect to ollama.test port 11434 after 1 ms: Could not connect to server',
            $request->toPsrRequest(),
        )));

        $this->expectException(OllamaConnectionFailed::class);

        $this->service()->chat($this->question());
    }

    public function test_a_read_that_times_out_after_the_headers_arrived_is_still_a_timeout(): void
    {
        // Guzzle 8 raises ResponseTimeoutException (it carries the partial response).
        Http::fake(fn (Request $request) => Create::rejectionFor(new ResponseTimeoutException(
            'cURL error 28: Operation timed out after 30001 milliseconds with 12 bytes received',
            $request->toPsrRequest(),
            new Psr7Response(200, ['Content-Type' => 'application/json'], '{"message":{"role":"assis'),
        )));

        $this->expectException(OllamaTimedOut::class);

        $this->service()->chat($this->question());
    }

    public function test_a_timeout_is_recognised_from_the_message_when_there_is_no_error_context(): void
    {
        Http::fake(Http::failedConnection('cURL error 28: Connection timed out after 3001 milliseconds'));

        $this->expectException(OllamaTimedOut::class);

        $this->service()->chat($this->question());
    }

    public function test_other_curl_errors_that_merely_contain_28_are_not_timeouts(): void
    {
        Http::fake(Http::failedConnection('cURL error 7: Failed to connect to ollama.test port 11428 after 28 ms'));

        $this->expectException(OllamaConnectionFailed::class);

        $this->service()->chat($this->question());
    }

    public function test_the_low_level_exception_is_not_chained(): void
    {
        Http::fake(Http::failedConnection('cURL error 7: Failed to connect to ollama.test'));

        try {
            $this->service()->chat($this->question());
            $this->fail('expected');
        } catch (OllamaConnectionFailed $e) {
            $this->assertNull($e->getPrevious()); // its message would carry the request URL
        }
    }

    // ---- malformed or unusable answers -----------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: string}> body, expected reason */
    public static function unusableBodies(): array
    {
        $message = fn (array $m, array $extra = []) => json_encode(['message' => $m, 'done' => true] + $extra);

        return [
            'empty body' => ['', 'not_json'],
            'not json' => ['<html>502 Bad Gateway</html>', 'not_json'],
            'truncated json' => ['{"message":{"role":"assistant","con', 'not_json'],
            'plain text' => ['Hello', 'not_json'],
            'json string' => ['"hello"', 'not_json'],
            'json number' => ['42', 'not_json'],
            'json null' => ['null', 'not_json'],
            'empty object' => ['{}', 'missing_message'],
            'empty list' => ['[]', 'missing_message'],
            'no message' => [json_encode(['done' => true]), 'missing_message'],
            'message is null' => [json_encode(['message' => null, 'done' => true]), 'missing_message'],
            'message is a string' => [json_encode(['message' => 'hi', 'done' => true]), 'missing_message'],
            'error field only' => [json_encode(['error' => 'something broke']), 'missing_message'],
            'no role' => [$message(['content' => 'hi']), 'unexpected_role'],
            'role is user' => [$message(['role' => 'user', 'content' => 'hi']), 'unexpected_role'],
            'role is not a string' => [$message(['role' => ['assistant'], 'content' => 'hi']), 'unexpected_role'],
            'no content' => [$message(['role' => 'assistant']), 'missing_content'],
            'content is null' => [$message(['role' => 'assistant', 'content' => null]), 'missing_content'],
            'content is a number' => [$message(['role' => 'assistant', 'content' => 42]), 'missing_content'],
            'content is a list' => [$message(['role' => 'assistant', 'content' => ['hi']]), 'missing_content'],
            'content is an object' => [$message(['role' => 'assistant', 'content' => ['text' => 'hi']]), 'missing_content'],
            'not finished' => [json_encode(['message' => ['role' => 'assistant', 'content' => 'par'], 'done' => false]), 'incomplete'],
            'done is a string' => [json_encode(['message' => ['role' => 'assistant', 'content' => 'hi'], 'done' => 'true']), 'incomplete'],
            'empty content' => [$message(['role' => 'assistant', 'content' => '']), 'empty_content'],
            'blank content' => [$message(['role' => 'assistant', 'content' => " \n\t "]), 'empty_content'],
        ];
    }

    #[DataProvider('unusableBodies')]
    public function test_an_unusable_answer_is_an_invalid_response(string $body, string $reason): void
    {
        Http::fake([self::BASE.'/*' => Http::response($body, 200, ['Content-Type' => 'application/json'])]);

        try {
            $this->service()->chat($this->question(self::PRIVATE_TEXT));
            $this->fail('An OllamaInvalidResponse was expected.');
        } catch (OllamaInvalidResponse $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame('The AI service returned an unusable response.', $e->getMessage());
            $this->assertNoLeak($e);
        }
    }

    public function test_a_success_status_without_content_is_an_invalid_response(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(null, 204)]);

        $this->expectException(OllamaInvalidResponse::class);

        $this->service()->chat($this->question());
    }

    public function test_extra_fields_in_a_good_answer_are_ignored(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->answer('Fine.') + ['total_duration' => 5, 'eval_count' => 3, 'message_extra' => 'x'])]);

        $this->assertSame('Fine.', $this->service()->chat($this->question()));
    }

    public function test_a_thinking_field_next_to_the_content_is_ignored(): void
    {
        $body = $this->answer('The answer.');
        $body['message']['thinking'] = 'private reasoning';
        Http::fake([self::BASE.'/*' => Http::response($body)]);

        $this->assertSame('The answer.', $this->service()->chat($this->question()));
    }

    // ---- bad input: refused before anything is sent ----------------------------------------------------------

    /** @return array<string, array{0: mixed}> */
    public static function badMessageLists(): array
    {
        return [
            'empty list' => [[]],
            'not a list' => [['role' => 'user', 'content' => 'hi']],
            'keys are not 0..n' => [[1 => ['role' => 'user', 'content' => 'hi']]],
            'message is a string' => [['hello']],
            'message is null' => [[null]],
            'no role' => [[['content' => 'hi']]],
            'no content' => [[['role' => 'user']]],
            'unknown role' => [[['role' => 'admin', 'content' => 'hi']]],
            'tool role is not supported' => [[['role' => 'tool', 'content' => 'hi']]],
            'role case' => [[['role' => 'User', 'content' => 'hi']]],
            'role not a string' => [[['role' => 1, 'content' => 'hi']]],
            'content not a string' => [[['role' => 'user', 'content' => 123]]],
            'content is a list' => [[['role' => 'user', 'content' => ['hi']]]],
            'content is empty' => [[['role' => 'user', 'content' => '']]],
            'content is blank' => [[['role' => 'user', 'content' => "  \n "]]],
            'one good one bad' => [[['role' => 'user', 'content' => 'hi'], ['role' => 'user', 'content' => '']]],
        ];
    }

    #[DataProvider('badMessageLists')]
    public function test_unusable_messages_are_refused_and_nothing_is_sent(array $messages): void
    {
        Http::fake();
        $refused = false;

        try {
            $this->service()->chat($messages);
        } catch (InvalidArgumentException) {
            $refused = true;
        }

        $this->assertTrue($refused, 'an InvalidArgumentException was expected');
        Http::assertNothingSent();
    }

    public function test_the_refusal_text_never_echoes_the_message_content(): void
    {
        try {
            $this->service()->chat([['role' => 'wizard', 'content' => self::PRIVATE_TEXT]]);
            $this->fail('expected');
        } catch (InvalidArgumentException $e) {
            $this->assertStringNotContainsString(self::PRIVATE_TEXT, $e->getMessage());
            $this->assertStringNotContainsString('wizard', $e->getMessage());
        }

        try {
            $this->service()->chat([['role' => 'user', 'content' => '   ']]);
            $this->fail('expected');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Message 0', $e->getMessage());
        }
    }

    // ---- bad configuration: refused before anything is sent --------------------------------------------------

    /** @return array<string, array{0: mixed}> */
    public static function badBaseUrls(): array
    {
        return [
            'empty' => [''], 'blank' => ['   '], 'null' => [null], 'number' => [11434], 'list' => [['http://x']],
            'no scheme' => ['localhost:11434'], 'relative' => ['//localhost:11434'], 'ftp' => ['ftp://localhost:11434'],
            'javascript' => ['javascript:alert(1)'], 'file' => ['file:///etc/passwd'], 'scheme only' => ['http://'],
            'credentials' => ['http://user:secret@localhost:11434'], 'user only' => ['http://user@localhost'],
            'query' => ['http://localhost:11434?x=1'], 'fragment' => ['http://localhost:11434#a'],
            'space' => ['http://local host:11434'], 'newline' => ["http://localhost:11434\n/evil"],
            'no host' => ['http:///path'], 'port only' => ['http://:11434'],
        ];
    }

    #[DataProvider('badBaseUrls')]
    public function test_an_unusable_base_url_is_refused_and_nothing_is_sent(mixed $url): void
    {
        config(['ollama.base_url' => $url]);
        Http::fake();

        try {
            $this->service()->chat($this->question(self::PRIVATE_TEXT));
            $this->fail('An OllamaMisconfigured was expected.');
        } catch (OllamaMisconfigured $e) {
            $this->assertSame('invalid_base_url', $e->reason);
            $this->assertSame('The AI service is not configured correctly.', $e->getMessage());
            $this->assertNoLeak($e);
        }

        Http::assertNothingSent();
    }

    /** @return array<string, array{0: mixed}> */
    public static function badModels(): array
    {
        return [
            'empty' => [''], 'blank' => ['   '], 'null' => [null], 'number' => [7], 'list' => [['gemma']],
            'space inside' => ['gemma 4'], 'newline' => ["gemma\nllama"], 'starts with dot' => ['.hidden'],
            'dot dot' => ['../x'], 'semicolon' => ['a;rm'], 'quote' => ['a"b'], 'too long' => [str_repeat('a', 201)],
        ];
    }

    #[DataProvider('badModels')]
    public function test_an_unusable_model_is_refused_and_nothing_is_sent(mixed $model): void
    {
        config(['ollama.model' => $model]);
        Http::fake();

        try {
            $this->service()->chat($this->question());
            $this->fail('An OllamaMisconfigured was expected.');
        } catch (OllamaMisconfigured $e) {
            $this->assertSame('invalid_model', $e->reason);
        }

        Http::assertNothingSent();
    }

    public function test_usual_model_names_are_accepted(): void
    {
        $this->fakeAnswer();

        foreach (['gemma4:31b-cloud', 'llama3.2:3b', 'library/gemma:7b', 'mistral', 'qwen2.5-coder:1.5b', 'hf.co/org/model:Q4_K_M'] as $model) {
            config(['ollama.model' => $model]);
            $this->assertSame('Hello from the model.', $this->service()->chat($this->question()), $model);
        }
    }

    // ---- exceptions, logging, privacy ------------------------------------------------------------------------

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

    public function test_every_failure_is_an_ollama_exception(): void
    {
        $this->fakeWhatever();

        $rounds = [
            OllamaConnectionFailed::class => fn () => Http::failedConnection('cURL error 7: x')($this->anyRequest()),
            OllamaTimedOut::class => fn () => Http::failedConnection('cURL error 28: x')($this->anyRequest()),
            OllamaHttpError::class => fn () => Http::response('x', 500),
            OllamaInvalidResponse::class => fn () => Http::response('x', 200),
        ];

        foreach ($rounds as $expected => $stub) {
            $this->nextStub = $stub;

            try {
                $this->service()->chat($this->question());
                $this->fail("expected {$expected}");
            } catch (OllamaException $e) {
                $this->assertSame($expected, $e::class);
            }
        }

        config(['ollama.model' => '']);

        try {
            $this->service()->chat($this->question());
            $this->fail('expected OllamaMisconfigured');
        } catch (OllamaException $e) {
            $this->assertSame(OllamaMisconfigured::class, $e::class);
        }
    }

    private function anyRequest(): Request
    {
        return new Request(new \GuzzleHttp\Psr7\Request('POST', self::BASE.'/api/chat'));
    }

    public function test_the_service_never_logs_on_success_or_on_failure(): void
    {
        Log::spy();
        $this->fakeWhatever();

        $stubs = [
            fn () => Http::response($this->answer()),
            fn () => Http::failedConnection('cURL error 7: x')($this->anyRequest()),
            fn () => Http::failedConnection('cURL error 28: x')($this->anyRequest()),
            fn () => Http::response('x', 500),
            fn () => Http::response('{}', 200),
        ];
        $outcomes = [];

        foreach ($stubs as $stub) {
            $this->nextStub = $stub;

            try {
                $this->service()->chat($this->question(self::PRIVATE_TEXT));
                $outcomes[] = 'answer';
            } catch (OllamaException $e) {
                $outcomes[] = $e::class;
            }
        }

        // Prove every path really ran, so "nothing was logged" is not true just because nothing happened.
        $this->assertSame(['answer', OllamaConnectionFailed::class, OllamaTimedOut::class, OllamaHttpError::class, OllamaInvalidResponse::class], $outcomes);

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_the_messages_are_not_kept_anywhere_after_a_call(): void
    {
        $this->fakeAnswer();
        $service = $this->service();

        $service->chat($this->question(self::PRIVATE_TEXT));

        $this->assertSame([], get_object_vars($service)); // no state at all: nothing to leak into the next call
    }

    public function test_consecutive_calls_are_independent(): void
    {
        Http::fake([self::BASE.'/*' => Http::sequence()
            ->push($this->answer('first'))
            ->push('boom', 500)
            ->push($this->answer('third'))]);
        $service = $this->service();

        $this->assertSame('first', $service->chat($this->question('one')));

        try {
            $service->chat($this->question('two'));
            $this->fail('expected');
        } catch (OllamaHttpError) {
        }

        $this->assertSame('third', $service->chat($this->question('three')));
    }

    private function assertNoLeak(OllamaException $e): void
    {
        $visible = $e->getMessage().' '.$e->reason.' '.$e::class;

        foreach ([self::PRIVATE_TEXT, 'ollama.test', '11434', 'test-model', 'http://', 'cURL', 'Bad Gateway', 'something broke'] as $secret) {
            $this->assertStringNotContainsString($secret, $visible, "exception text leaked [{$secret}]");
        }
    }
}
