<?php

namespace Tests\Unit\Services\AI\Embeddings;

use App\Services\AI\Embeddings\EmbeddingMisconfigured;
use App\Services\AI\Embeddings\GoogleAiConfig;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** How GoogleAiConfig reads and validates config/google_ai.php. Every key used here is an obvious fake. */
class GoogleAiConfigTest extends TestCase
{
    private const FAKE_KEY = 'test-key-not-real-0123456789';

    private string|false $savedIgnoreArgs;

    private string|false $savedParamLength;

    protected function setUp(): void
    {
        parent::setUp();

        // Record stack-trace arguments the way PHP does without a php.ini (as in the Docker image), whatever the machine's own
        // settings are, so the leak assertions below always look at the worst case.
        $this->savedIgnoreArgs = ini_set('zend.exception_ignore_args', '0');
        $this->savedParamLength = ini_set('zend.exception_string_param_max_len', '15');
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

    private function refused(callable $read, string $reason): void
    {
        try {
            $read();
            $this->fail('An unusable setting was accepted.');
        } catch (EmbeddingMisconfigured $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame('The embedding service is not configured correctly.', $e->getMessage());
        }
    }

    // ---- api key ---------------------------------------------------------------------------------------------

    public function test_a_valid_key_is_returned_trimmed(): void
    {
        config(['google_ai.api_key' => '  '.self::FAKE_KEY."\n"]);

        $this->assertSame(self::FAKE_KEY, GoogleAiConfig::apiKey());
    }

    /** @return array<string, array{mixed}> */
    public static function missingKeys(): array
    {
        return ['not set' => [null], 'empty' => [''], 'spaces' => ['   '], 'newline' => ["\n"]];
    }

    #[DataProvider('missingKeys')]
    public function test_a_missing_key_is_refused(mixed $value): void
    {
        config(['google_ai.api_key' => $value]);

        $this->refused(fn () => GoogleAiConfig::apiKey(), 'missing_api_key');
    }

    /**
     * The malformed keys. They are looked up by NAME inside the test and never handed to a test method as an argument: PHPUnit
     * puts the arguments of the test method into the stack-trace frame that calls it, and PHP (with its default settings, as
     * in the Docker image) writes the first 15 characters of every string argument into (string) $exception. A key-shaped
     * data-set value would then show up in the very output the test inspects, which is a property of the test and not of the
     * code under test. With the name only, the assertion below can stay as strict as it is: the WHOLE exception text, trace
     * included, must not contain any part of the key.
     *
     * @return array<string, mixed>
     */
    private static function malformedKeys(): array
    {
        return [
            'too short' => 'short-key',
            'too long' => str_repeat('a', 513),
            'a space inside' => 'test-key not-real-0123456789',
            'a line break inside' => "test-key-not-real\nx-injected: 1",
            'a carriage return' => "test-key-not-real\r\n0123456789",
            'quotes left in' => '"test-key-not-real-0123456789"',
            'a comma' => 'test-key-not-real,0123456789',
            'non-ASCII' => 'test-key-not-real-০১২৩৪৫৬৭৮৯',
            'an array' => ['test-key-not-real-0123456789'],
            'a number' => 12345678901234567890,
            'true' => true,
        ];
    }

    /** @return array<string, array{string}> */
    public static function badKeys(): array
    {
        return array_map(fn (string $name) => [$name], array_combine(array_keys(self::malformedKeys()), array_keys(self::malformedKeys())));
    }

    #[DataProvider('badKeys')]
    public function test_a_malformed_key_is_refused_without_quoting_it(string $case): void
    {
        config(['google_ai.api_key' => self::malformedKeys()[$case]]);

        try {
            GoogleAiConfig::apiKey();
            $this->fail('A malformed key was accepted.');
        } catch (EmbeddingMisconfigured $e) {
            $this->assertContains($e->reason, ['invalid_api_key', 'missing_api_key']);
            $this->assertSame('The embedding service is not configured correctly.', $e->getMessage());
            $this->assertNull($e->getPrevious());
            // The whole text, file names and stack trace with its arguments included.
            $this->assertStringNotContainsString('test-key', (string) $e);
            $this->assertStringNotContainsString('x-injected', (string) $e);
        }
    }

    public function test_the_check_above_can_fail(): void
    {
        // Proof that (string) $e does show string arguments under PHP's default settings, so the assertion is not vacuous.
        $function = static function (string $argument): never {
            throw new \RuntimeException('boom');
        };

        try {
            $function('test-key-not-real-0123456789');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('test-key', (string) $e);
        }
    }

    public function test_a_key_with_the_usual_symbols_is_accepted(): void
    {
        config(['google_ai.api_key' => 'Abc_def-ghi.jkl~mno+pqr/stu=vwx']);

        $this->assertSame('Abc_def-ghi.jkl~mno+pqr/stu=vwx', GoogleAiConfig::apiKey());
    }

    // ---- base url --------------------------------------------------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function goodBaseUrls(): array
    {
        return [
            'google' => ['https://generativelanguage.googleapis.com/v1beta', 'https://generativelanguage.googleapis.com/v1beta'],
            'trailing slash' => ['https://generativelanguage.googleapis.com/v1beta/', 'https://generativelanguage.googleapis.com/v1beta'],
            'spaces around' => ['  https://google.test/v1  ', 'https://google.test/v1'],
            'a port' => ['https://google.test:8443/v1beta', 'https://google.test:8443/v1beta'],
            'upper case scheme' => ['HTTPS://google.test/v1', 'HTTPS://google.test/v1'],
            'localhost over http' => ['http://localhost:9999/v1beta', 'http://localhost:9999/v1beta'],
            'loopback over http' => ['http://127.0.0.1:9999', 'http://127.0.0.1:9999'],
            'ipv6 loopback over http' => ['http://[::1]:9999/x', 'http://[::1]:9999/x'],
        ];
    }

    #[DataProvider('goodBaseUrls')]
    public function test_usable_base_urls_are_accepted(string $configured, string $expected): void
    {
        config(['google_ai.base_url' => $configured]);

        $this->assertSame($expected, GoogleAiConfig::baseUrl());
    }

    /** @return array<string, array{mixed}> */
    public static function badBaseUrls(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'spaces' => ['   '],
            'an array' => [['https://google.test']],
            'no scheme' => ['generativelanguage.googleapis.com/v1beta'],
            'ftp' => ['ftp://google.test'],
            'file' => ['file:///etc/passwd'],
            'plain http to a real host (the key would travel in the clear)' => ['http://generativelanguage.googleapis.com/v1beta'],
            'plain http to a look-alike of localhost' => ['http://localhost.evil.test/v1'],
            'plain http to the docker host' => ['http://host.docker.internal:9999'],
            'credentials in the address' => ['https://user:pass@google.test/v1'],
            'an at sign in the path' => ['https://google.test/v1@evil.test'],
            'a query string' => ['https://google.test/v1?key=abc'],
            'a fragment' => ['https://google.test/v1#x'],
            'whitespace inside' => ['https://google.test/v1 beta'],
            'a line break inside' => ["https://google.test/v1\nHost: evil.test"],
            'no host' => ['https:///v1beta'],
            'only the scheme' => ['https://'],
        ];
    }

    #[DataProvider('badBaseUrls')]
    public function test_unusable_base_urls_are_refused(mixed $value): void
    {
        config(['google_ai.base_url' => $value]);

        $this->refused(fn () => GoogleAiConfig::baseUrl(), 'invalid_base_url');
    }

    // ---- base URL: the plain-http loopback exception ---------------------------------------------------------
    //
    // The key is sent in a header, so plain http is only tolerable when the request never leaves the machine.
    // The exception is an exact match on the host, not a prefix or a "looks local" test.

    /** @return array<string, array{string}> */
    public static function loopbackHttpUrls(): array
    {
        return [
            'ipv4, port and path' => ['http://127.0.0.1:8080/v1beta'],
            'ipv4, port only' => ['http://127.0.0.1:8080'],
            'ipv4, bare' => ['http://127.0.0.1'],
            'ipv4, path only' => ['http://127.0.0.1/v1beta'],
            'ipv6, port and path' => ['http://[::1]:8080/v1beta'],
            'ipv6, port only' => ['http://[::1]:8080'],
            'ipv6, bare' => ['http://[::1]'],
            'ipv6, path only' => ['http://[::1]/v1beta'],
            'localhost, port and path' => ['http://localhost:8080/v1beta'],
            'localhost, bare' => ['http://localhost'],
            'localhost in capitals (host names are case-insensitive)' => ['HTTP://LOCALHOST:8080/v1beta'],
        ];
    }

    #[DataProvider('loopbackHttpUrls')]
    public function test_plain_http_is_accepted_only_for_loopback_and_returned_unchanged(string $url): void
    {
        config(['google_ai.base_url' => $url]);

        $this->assertSame($url, GoogleAiConfig::baseUrl());
    }

    /** @return array<string, array{string}> */
    public static function nonLoopbackHttpUrls(): array
    {
        return [
            // Ordinary hosts, including the real Google one: the key would cross the network in the clear.
            'a generic host' => ['http://example.test/v1beta'],
            'a generic host with a port' => ['http://example.test:8080/v1beta'],
            'the real google host' => ['http://generativelanguage.googleapis.com/v1beta'],
            // Look-alikes: the host merely starts or ends with, or contains, a loopback spelling.
            'localhost as a sub-domain prefix' => ['http://localhost.evil.test:8080'],
            'localhost as a sub-domain suffix' => ['http://evil-localhost:8080'],
            'ipv4 loopback as a sub-domain prefix' => ['http://127.0.0.1.evil.test:8080'],
            'ipv4 loopback as a suffix' => ['http://evil.test.127.0.0.1:8080'],
            'localhost with a trailing dot' => ['http://localhost.:8080'],
            'ipv4 loopback with a trailing dot' => ['http://127.0.0.1.:8080'],
            // Other addresses that are local or reserved but are NOT the three exact spellings. Rejected on purpose:
            // an allow-list of exact spellings fails closed, and a spelling nobody listed is never trusted.
            'another address in 127.0.0.0/8' => ['http://127.0.0.2:8080'],
            'the short form of loopback' => ['http://127.1:8080'],
            'the unspecified ipv4 address' => ['http://0.0.0.0:8080'],
            'the unspecified ipv6 address' => ['http://[::]:8080'],
            'ipv6 loopback written out in full' => ['http://[0:0:0:0:0:0:0:1]:8080'],
            'ipv6 loopback with zero padding' => ['http://[0000:0000:0000:0000:0000:0000:0000:0001]:8080'],
            'ipv4-mapped ipv6 loopback' => ['http://[::ffff:127.0.0.1]:8080'],
            'an ipv6 zone identifier' => ['http://[::1%25lo]:8080'],
            'a different ipv6 loopback-like address' => ['http://[::2]:8080'],
            'a public ipv6 address' => ['http://[2001:db8::1]:8080'],
            'a public ipv4 address' => ['http://8.8.8.8:8080'],
            'a private ipv4 address' => ['http://10.0.0.5:8080'],
            'the cloud metadata address' => ['http://169.254.169.254/v1beta'],
            'the docker host name' => ['http://host.docker.internal:8080'],
            // Tricks that try to make the validator and the HTTP client disagree about the host.
            'a backslash after the loopback host' => ['http://127.0.0.1\\@evil.test'],
            'a user-info section naming loopback' => ['http://127.0.0.1@evil.test'],
            'a user-info section with loopback as the user and a port' => ['http://127.0.0.1:80@evil.test'],
            'loopback in the user-info of an ipv6 host' => ['http://[::1]@evil.test'],
            'a second port' => ['http://127.0.0.1:8080:9090'],
            'a non-numeric port' => ['http://127.0.0.1:evil.test'],
            'an unclosed ipv6 bracket' => ['http://[::1:8080'],
            'a doubled ipv6 bracket' => ['http://[::1]]:8080'],
            'an encoded NUL after the host' => ['http://127.0.0.1%00.evil.test'],
            'a semicolon after the host' => ['http://127.0.0.1;evil.test'],
            'an ideographic full stop' => ['http://localhost。evil.test'],
        ];
    }

    #[DataProvider('nonLoopbackHttpUrls')]
    public function test_plain_http_to_anything_but_the_three_loopback_spellings_is_refused(string $url): void
    {
        config(['google_ai.base_url' => $url]);

        $this->refused(fn () => GoogleAiConfig::baseUrl(), 'invalid_base_url');
    }

    /**
     * The next two tests pin the behaviour the loopback check relies on. If a PHP release changed how parse_url
     * reports an IPv6 host (today it keeps the square brackets), the first would fail and say why, instead of the
     * IPv6 exception silently stopping to work or, worse, starting to match something else.
     */
    public function test_parse_url_reports_an_ipv6_host_with_its_brackets(): void
    {
        $parts = parse_url('http://[::1]:8080');

        $this->assertIsArray($parts);
        $this->assertSame('http', $parts['scheme']);
        $this->assertSame('[::1]', $parts['host']);
        $this->assertSame(8080, $parts['port']);
        $this->assertSame('[::1]', parse_url('http://[::1]', PHP_URL_HOST));
        $this->assertSame('127.0.0.1', parse_url('http://127.0.0.1:8080', PHP_URL_HOST));
    }

    /**
     * What the validator accepts must be what the HTTP client will connect to. Laravel's client is Guzzle, so for
     * every plain-http URL the validator accepts, Guzzle's own parser has to see one of the three loopback hosts.
     */
    #[DataProvider('loopbackHttpUrls')]
    public function test_the_http_client_reads_an_accepted_http_url_as_the_same_loopback_host(string $url): void
    {
        config(['google_ai.base_url' => $url]);

        $accepted = GoogleAiConfig::baseUrl();

        $this->assertContains(
            (new Uri($accepted))->getHost(),
            ['localhost', '127.0.0.1', '[::1]'],
        );
    }

    // ---- base URL: https is not narrowed by the loopback rule ------------------------------------------------

    /** @return array<string, array{string}> */
    public static function httpsUrls(): array
    {
        return [
            'google' => ['https://generativelanguage.googleapis.com/v1beta'],
            'a test host' => ['https://google.test:8443/v1beta'],
            'ipv4 loopback' => ['https://127.0.0.1:8443/v1beta'],
            'ipv6 loopback' => ['https://[::1]:8443/v1beta'],
            'localhost' => ['https://localhost:8443/v1beta'],
            'a private address (a gateway or proxy inside the network)' => ['https://10.0.0.5:8443/v1beta'],
            'an internal host name' => ['https://embeddings.internal.example/v1beta'],
        ];
    }

    /**
     * The base URL is operator-only configuration, so https to any host is allowed, exactly as before. A private
     * or internal address is not blocked here: that would stop legitimate gateways, and could not stop an attacker
     * who controls DNS anyway. Only the credentials, query, fragment and whitespace rules still apply to https.
     */
    #[DataProvider('httpsUrls')]
    public function test_https_is_accepted_for_any_host_and_returned_unchanged(string $url): void
    {
        config(['google_ai.base_url' => $url]);

        $this->assertSame($url, GoogleAiConfig::baseUrl());
    }

    /** @return array<string, array{string}> */
    public static function badHttpsUrls(): array
    {
        return [
            'credentials' => ['https://user:pass@127.0.0.1/v1'],
            'credentials in front of a loopback look-alike' => ['https://127.0.0.1@evil.test/v1'],
            'a query string on loopback' => ['https://127.0.0.1/v1?key=abc'],
            'a fragment on loopback' => ['https://[::1]/v1#x'],
            'no host' => ['https:///v1'],
            'a space in the host' => ['https://127.0.0.1 evil.test/v1'],
        ];
    }

    #[DataProvider('badHttpsUrls')]
    public function test_the_https_rules_are_not_loosened_by_the_loopback_exception(string $url): void
    {
        config(['google_ai.base_url' => $url]);

        $this->refused(fn () => GoogleAiConfig::baseUrl(), 'invalid_base_url');
    }

    // ---- model -----------------------------------------------------------------------------------------------

    public function test_the_usual_model_names_are_accepted(): void
    {
        foreach (['gemini-embedding-001', 'gemini-embedding-2', 'text-embedding-004', 'embedding_001.v2'] as $name) {
            config(['google_ai.embedding_model' => " {$name} "]);

            $this->assertSame($name, GoogleAiConfig::model());
        }
    }

    /** @return array<string, array{mixed}> */
    public static function badModels(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'spaces' => ['  '],
            'an array' => [['gemini-embedding-001']],
            'a models/ prefix' => ['models/gemini-embedding-001'],
            'a colon (could change the method called)' => ['gemini-embedding-001:batchEmbedContents'],
            'a slash' => ['a/b'],
            'path climbing' => ['../v1/other'],
            'a query string' => ['gemini?key=x'],
            'a space inside' => ['gemini embedding'],
            'a line break' => ["gemini\nx"],
            'starts with a dash' => ['-gemini'],
            'too long' => [str_repeat('a', 101)],
        ];
    }

    #[DataProvider('badModels')]
    public function test_unusable_model_names_are_refused(mixed $value): void
    {
        config(['google_ai.embedding_model' => $value]);

        $this->refused(fn () => GoogleAiConfig::model(), 'invalid_model');
    }

    // ---- dimensions ------------------------------------------------------------------------------------------

    /** @return array<string, array{mixed, int}> */
    public static function goodDimensions(): array
    {
        return [
            'int 1536' => [1536, 1536],
            'string 1536 (from the environment)' => ['1536', 1536],
            'string with spaces' => [' 768 ', 768],
            'leading zeros' => ['00768', 768],
            'smallest' => [128, 128],
            'largest' => [3072, 3072],
            'largest as a string' => ['3072', 3072],
        ];
    }

    #[DataProvider('goodDimensions')]
    public function test_valid_dimensions_are_accepted(mixed $configured, int $expected): void
    {
        config(['google_ai.embedding_dimensions' => $configured]);

        $this->assertSame($expected, GoogleAiConfig::dimensions());
    }

    /** @return array<string, array{mixed}> */
    public static function badDimensions(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'spaces' => ['  '],
            'zero' => [0],
            'one below the minimum' => [127],
            'one above the maximum' => [3073],
            'negative' => [-768],
            'a huge number' => [99999999999],
            'a word' => ['abc'],
            'digits and a letter' => ['15x'],
            'a decimal string' => ['1536.5'],
            'a float' => [1536.5],
            'a float that looks whole' => [1536.0],
            'scientific notation' => ['1.536e3'],
            'a sign' => ['+1536'],
            'true' => [true],
            'an array' => [[1536]],
        ];
    }

    #[DataProvider('badDimensions')]
    public function test_invalid_dimensions_are_refused_never_corrected(mixed $value): void
    {
        config(['google_ai.embedding_dimensions' => $value]);

        $this->refused(fn () => GoogleAiConfig::dimensions(), 'invalid_dimensions');
    }

    // ---- timeouts and batch size (these fall back) -----------------------------------------------------------

    public function test_timeouts_are_read_as_seconds(): void
    {
        config(['google_ai.connect_timeout' => '2', 'google_ai.timeout' => 45]);

        $this->assertSame(2.0, GoogleAiConfig::connectTimeout());
        $this->assertSame(45.0, GoogleAiConfig::timeout());
    }

    /** @return array<string, array{mixed}> */
    public static function badTimeouts(): array
    {
        return [
            'null' => [null], 'empty' => [''], 'zero' => [0], 'zero string' => ['0'], 'negative' => [-5], 'a word' => ['soon'],
            'infinity' => [INF], 'not a number' => [NAN], 'above the maximum' => [301], 'an array' => [[10]], 'true' => [true],
        ];
    }

    #[DataProvider('badTimeouts')]
    public function test_a_bad_timeout_falls_back_to_the_default_and_never_means_forever(mixed $value): void
    {
        config(['google_ai.connect_timeout' => $value, 'google_ai.timeout' => $value]);

        $this->assertSame((float) GoogleAiConfig::DEFAULT_CONNECT_TIMEOUT, GoogleAiConfig::connectTimeout());
        $this->assertSame((float) GoogleAiConfig::DEFAULT_TIMEOUT, GoogleAiConfig::timeout());
    }

    public function test_batch_size_accepts_one_to_one_hundred(): void
    {
        foreach ([[1, 1], ['25', 25], [100, 100], [' 7 ', 7]] as [$configured, $expected]) {
            config(['google_ai.batch_size' => $configured]);

            $this->assertSame($expected, GoogleAiConfig::batchSize());
        }
    }

    /** @return array<string, array{mixed}> */
    public static function badBatchSizes(): array
    {
        return ['null' => [null], 'empty' => [''], 'zero' => [0], 'negative' => [-1], 'above 100' => [101], 'a word' => ['many'], 'a float' => [10.5], 'an array' => [[5]]];
    }

    #[DataProvider('badBatchSizes')]
    public function test_a_bad_batch_size_falls_back_to_the_default(mixed $value): void
    {
        config(['google_ai.batch_size' => $value]);

        $this->assertSame(GoogleAiConfig::DEFAULT_BATCH_SIZE, GoogleAiConfig::batchSize());
    }

    public function test_the_defaults_match_the_documented_choices(): void
    {
        $this->assertSame(1536, GoogleAiConfig::DEFAULT_DIMENSIONS);
        $this->assertSame(128, GoogleAiConfig::MIN_DIMENSIONS);
        $this->assertSame(3072, GoogleAiConfig::MAX_DIMENSIONS);
        $this->assertSame(5, GoogleAiConfig::DEFAULT_CONNECT_TIMEOUT);
        $this->assertSame(30, GoogleAiConfig::DEFAULT_TIMEOUT);
        $this->assertSame(50, GoogleAiConfig::DEFAULT_BATCH_SIZE);
        $this->assertSame(100, GoogleAiConfig::MAX_BATCH_SIZE);
    }

    public function test_settings_are_read_at_call_time_not_remembered(): void
    {
        config(['google_ai.embedding_dimensions' => 768]);
        $this->assertSame(768, GoogleAiConfig::dimensions());

        config(['google_ai.embedding_dimensions' => 1536]);
        $this->assertSame(1536, GoogleAiConfig::dimensions());
    }
}
