<?php

namespace App\Exceptions;

use App\Auth\Supabase\AccountNotProvisionable;
use App\Auth\Supabase\AuthServiceUnavailable;
use App\Auth\Supabase\EmailConflict;
use App\Auth\Supabase\InvalidSupabaseToken;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns every exception raised for an API request into the standard JSON envelope (see ApiResponse),
 * with the right status code, and never leaks internals (class names, SQL, stack traces) to the client.
 *
 * Laravel has already converted ModelNotFoundException to a 404 and AuthorizationException to a 403
 * HttpException by the time these callbacks run.
 *
 * Supabase authentication failures get fixed, generic public messages (never the internal $reason code, the exception
 * message, or anything from the token) and are mapped BEFORE the generic 500 branch, so they look the same whether or
 * not APP_DEBUG is on:
 *
 *   InvalidSupabaseToken     401  + WWW-Authenticate: Bearer ... error="invalid_token"
 *   AuthServiceUnavailable   503  + Retry-After: 5
 *   EmailConflict            409
 *   AccountNotProvisionable  403
 *
 * Each of these is logged exactly once, as one structured line carrying only the reason code, and Laravel's default
 * reporting of them is switched off (it would add a second entry with the exception message and its previous chain).
 */
final class ApiExceptionHandler
{
    /** Challenge sent with every 401 so clients know a Bearer token is expected. */
    private const CHALLENGE = 'Bearer realm="LawAssist API"';

    private const CHALLENGE_INVALID_TOKEN = 'Bearer realm="LawAssist API", error="invalid_token"';

    /** Seconds a client should wait before retrying after a 503. */
    private const RETRY_AFTER_SECONDS = '5';

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => self::isApiRequest($request),
        );

        // One structured log line per authentication failure, then stop: no second, default log entry.
        $exceptions->report(function (
            InvalidSupabaseToken|AuthServiceUnavailable|EmailConflict|AccountNotProvisionable $e,
        ): void {
            self::logAuthFailure($e);
        })->stop();

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! self::isApiRequest($request)) {
                return null; // leave non-API requests to Laravel's defaults
            }

            return self::toResponse($e);
        });
    }

    public static function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function toResponse(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return ApiResponse::error('The given data was invalid.', 422, $e->errors());
        }

        if ($e instanceof InvalidSupabaseToken) {
            return ApiResponse::error(
                'Invalid or expired access token.',
                401,
                null,
                ['WWW-Authenticate' => self::CHALLENGE_INVALID_TOKEN],
            );
        }

        if ($e instanceof AuthServiceUnavailable) {
            return ApiResponse::error(
                'Authentication service unavailable.',
                503,
                null,
                ['Retry-After' => self::RETRY_AFTER_SECONDS],
            );
        }

        if ($e instanceof EmailConflict) {
            return ApiResponse::error('An account with this email already exists.', 409);
        }

        if ($e instanceof AccountNotProvisionable) {
            return ApiResponse::error('Your account cannot be set up because it has no usable email address.', 403);
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error('Unauthenticated.', 401, null, ['WWW-Authenticate' => self::CHALLENGE]);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return ApiResponse::error(self::messageFor($status, $e), $status, null, $e->getHeaders());
        }

        // Anything else is a bug on our side: log it (Laravel does this before rendering) and say nothing specific.
        return ApiResponse::error(
            'Server error.',
            500,
            config('app.debug') ? ['debug' => [get_class($e).': '.$e->getMessage()]] : null,
        );
    }

    /**
     * Logs ONLY the reason code. Never the exception message, its previous exception, the token, claims or an email.
     * Logging is best effort: a broken log channel must not turn a 401/503 into a 500.
     */
    private static function logAuthFailure(
        InvalidSupabaseToken|AuthServiceUnavailable|EmailConflict|AccountNotProvisionable $e,
    ): void {
        $context = ['reason' => $e->reason];

        try {
            match (true) {
                $e instanceof AuthServiceUnavailable => Log::error('Supabase authentication is unavailable.', $context),
                $e instanceof InvalidSupabaseToken => Log::warning('Supabase access token rejected.', $context),
                $e instanceof EmailConflict => Log::warning('Supabase user provisioning refused: email already in use.', $context),
                default => Log::warning('Supabase user provisioning refused: no usable email.', $context),
            };
        } catch (Throwable) {
            // Best effort only.
        }
    }

    private static function messageFor(int $status, HttpExceptionInterface $e): string
    {
        return match ($status) {
            401 => 'Unauthenticated.',
            403 => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            419 => 'Page expired.',
            429 => 'Too many requests. Please try again later.',
            default => $status >= 500
                ? 'Server error.'
                : ($e->getMessage() !== '' ? $e->getMessage() : 'Request could not be processed.'),
        };
    }
}
