<?php

namespace App\Services\AI\Embeddings;

/**
 * Typed, validated access to config/google_ai.php. Read at call time (not cached in properties) so tests can change it.
 *
 * Settings that decide WHAT is sent or WHERE (key, base URL, model, dimensions) are refused when unusable
 * (EmbeddingMisconfigured, with a reason that names the setting and never shows the value): there is no sensible guess, and
 * a wrong size would silently poison stored vectors. Timeouts and the batch size fall back to their defaults instead,
 * because a timeout of 0 would mean "wait forever" to the HTTP client and a bad batch size is harmless.
 */
final class GoogleAiConfig
{
    public const DEFAULT_DIMENSIONS = 1536;

    /** The range Google accepts for gemini-embedding-001's outputDimensionality. */
    public const MIN_DIMENSIONS = 128;

    public const MAX_DIMENSIONS = 3072;

    public const DEFAULT_CONNECT_TIMEOUT = 5;

    public const DEFAULT_TIMEOUT = 30;

    public const MAX_TIMEOUT = 300;

    public const DEFAULT_BATCH_SIZE = 50;

    public const MAX_BATCH_SIZE = 100;

    /** Letters, digits and the few symbols keys use, no whitespace or control characters (so it can not break a header). */
    private const API_KEY = '/\A[A-Za-z0-9_\-.~+\/=]{16,512}\z/';

    /** http(s)://host[:port][/path], with no credentials, query or fragment, and no whitespace. */
    private const BASE_URL = '/\Ahttps?:\/\/[^\s?#@]+\z/i';

    /** A bare model name such as gemini-embedding-001. It goes into the request path, so no "/" or ":" is allowed. */
    private const MODEL = '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/';

    /** @throws EmbeddingMisconfigured */
    public static function apiKey(): string
    {
        $key = config('google_ai.api_key');

        if (! is_string($key) || trim($key) === '') {
            throw new EmbeddingMisconfigured('missing_api_key');
        }

        $key = trim($key);

        if (preg_match(self::API_KEY, $key) !== 1) {
            throw new EmbeddingMisconfigured('invalid_api_key');
        }

        return $key;
    }

    /**
     * https only, because the key is sent along. Plain http is accepted for the local machine alone, for test servers.
     *
     * @throws EmbeddingMisconfigured
     */
    public static function baseUrl(): string
    {
        $url = config('google_ai.base_url');

        if (! is_string($url)) {
            throw new EmbeddingMisconfigured('invalid_base_url');
        }

        $url = rtrim(trim($url), '/');

        if (preg_match(self::BASE_URL, $url) !== 1) {
            throw new EmbeddingMisconfigured('invalid_base_url');
        }

        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! is_string($host) || $host === '') {
            throw new EmbeddingMisconfigured('invalid_base_url');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'https' && ! ($scheme === 'http' && in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]'], true))) {
            throw new EmbeddingMisconfigured('invalid_base_url');
        }

        return $url;
    }

    /** @throws EmbeddingMisconfigured */
    public static function model(): string
    {
        $model = config('google_ai.embedding_model');

        if (! is_string($model) || preg_match(self::MODEL, trim($model)) !== 1) {
            throw new EmbeddingMisconfigured('invalid_model');
        }

        return trim($model);
    }

    /**
     * A whole number from MIN_DIMENSIONS to MAX_DIMENSIONS, as an int or a string of digits (the environment gives strings).
     *
     * @throws EmbeddingMisconfigured
     */
    public static function dimensions(): int
    {
        $value = config('google_ai.embedding_dimensions');

        if (is_string($value) && preg_match('/\A[0-9]{1,5}\z/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        if (! is_int($value) || $value < self::MIN_DIMENSIONS || $value > self::MAX_DIMENSIONS) {
            throw new EmbeddingMisconfigured('invalid_dimensions');
        }

        return $value;
    }

    public static function connectTimeout(): float
    {
        return self::seconds(config('google_ai.connect_timeout'), self::DEFAULT_CONNECT_TIMEOUT);
    }

    public static function timeout(): float
    {
        return self::seconds(config('google_ai.timeout'), self::DEFAULT_TIMEOUT);
    }

    public static function batchSize(): int
    {
        $value = config('google_ai.batch_size');

        if (is_string($value) && preg_match('/\A[0-9]{1,5}\z/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        return is_int($value) && $value >= 1 && $value <= self::MAX_BATCH_SIZE ? $value : self::DEFAULT_BATCH_SIZE;
    }

    private static function seconds(mixed $value, int $default): float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return (float) $default;
        }

        $seconds = (float) $value;

        return is_finite($seconds) && $seconds > 0 && $seconds <= self::MAX_TIMEOUT ? $seconds : (float) $default;
    }
}
