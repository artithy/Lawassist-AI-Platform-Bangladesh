<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns every exception raised for an API request into the standard JSON envelope (see ApiResponse),
 * with the right status code, and never leaks internals (class names, SQL, stack traces) to the client.
 *
 * Laravel has already converted ModelNotFoundException to a 404 and AuthorizationException to a 403
 * HttpException by the time these callbacks run.
 */
final class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => self::isApiRequest($request),
        );

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

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error('Unauthenticated.', 401);
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
