<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The rate limits of the /api routes (named limiter "lawassist-api", applied to the whole api group in bootstrap/app.php).
 *
 * Throttling runs BEFORE authentication, so it can only use what the request carries:
 *  - the client address (every request), and
 *  - when an Authorization header is sent, a SHA-256 of it (one signed-in client). The header itself is never stored in
 *    a cache key, a log or a response.
 * Both limits apply; the first one exceeded answers 429 with Retry-After (rendered by ApiExceptionHandler).
 *
 * Behind a reverse proxy or load balancer the address is the proxy's unless trusted proxies are configured
 * (bootstrap/app.php: $middleware->trustProxies(...)): then everybody shares one "per_ip" allowance.
 */
final class ApiRateLimit
{
    public const NAME = 'lawassist-api';

    public const DEFAULT_PER_IP = 300;

    public const DEFAULT_PER_TOKEN = 120;

    public static function register(): void
    {
        RateLimiter::for(self::NAME, fn (Request $request) => self::limits($request));
    }

    /** @return list<Limit> */
    public static function limits(Request $request): array
    {
        $limits = [
            Limit::perMinute(self::allowance('per_ip', self::DEFAULT_PER_IP))->by('ip:'.($request->ip() ?? 'unknown')),
        ];

        $authorization = $request->headers->get('authorization');

        if (is_string($authorization) && $authorization !== '') {
            $limits[] = Limit::perMinute(self::allowance('per_token', self::DEFAULT_PER_TOKEN))
                ->by('authorization:'.hash('sha256', $authorization));
        }

        return $limits;
    }

    private static function allowance(string $key, int $default): int
    {
        $value = config("api.rate_limit.{$key}");

        if (is_string($value)) {
            $value = trim($value);
        }

        // Only a positive whole number counts. Anything else (null, '', 'abc', 0, -5, 1.5) means "use the default".
        if (is_int($value)) {
            return $value > 0 ? $value : $default;
        }

        return is_string($value) && preg_match('/\A[1-9][0-9]{0,8}\z/', $value) === 1 ? (int) $value : $default;
    }
}
