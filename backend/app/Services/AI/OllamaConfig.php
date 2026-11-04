<?php

namespace App\Services\AI;

/**
 * Typed, validated access to config/ollama.php. Read at call time (not cached in properties) so tests can change it.
 *
 * Base URL and model are refused when unusable (OllamaMisconfigured): there is no sensible guess. A bad timeout falls back
 * to the default instead, because a timeout of 0 would mean "wait forever" to the HTTP client.
 */
final class OllamaConfig
{
    public const DEFAULT_CONNECT_TIMEOUT = 5;

    public const DEFAULT_TIMEOUT = 120;

    private const MAX_TIMEOUT = 3600;

    /** http(s)://host[:port][/path], with no credentials, query or fragment, and no whitespace. */
    private const BASE_URL = '/\Ahttps?:\/\/[^\s?#@]+\z/i';

    /** Names such as gemma4:31b-cloud, llama3.2:3b or library/gemma:7b. Must start with a letter or digit. */
    private const MODEL = '/\A[A-Za-z0-9][A-Za-z0-9._:\/@-]{0,199}\z/';

    /** @throws OllamaMisconfigured */
    public static function baseUrl(): string
    {
        $url = config('ollama.base_url');

        if (! is_string($url)) {
            throw new OllamaMisconfigured('invalid_base_url');
        }

        $url = rtrim(trim($url), '/');

        if (preg_match(self::BASE_URL, $url) !== 1) {
            throw new OllamaMisconfigured('invalid_base_url');
        }

        $parts = parse_url($url);

        if ($parts === false || ! is_string($parts['host'] ?? null) || $parts['host'] === '') {
            throw new OllamaMisconfigured('invalid_base_url');
        }

        return $url;
    }

    /** @throws OllamaMisconfigured */
    public static function model(): string
    {
        $model = config('ollama.model');

        if (! is_string($model) || preg_match(self::MODEL, trim($model)) !== 1) {
            throw new OllamaMisconfigured('invalid_model');
        }

        return trim($model);
    }

    public static function connectTimeout(): float
    {
        return self::seconds(config('ollama.connect_timeout'), self::DEFAULT_CONNECT_TIMEOUT);
    }

    public static function timeout(): float
    {
        return self::seconds(config('ollama.timeout'), self::DEFAULT_TIMEOUT);
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
