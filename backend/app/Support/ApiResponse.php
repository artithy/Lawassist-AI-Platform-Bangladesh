<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * The one JSON shape every API endpoint uses.
 *
 * Success: { "success": true,  "message": "...", "data": ... }
 * Failure: { "success": false, "message": "...", "errors": { ... } }   ("errors" only when there is detail)
 *
 * It matches the existing GET /api/health response, which already uses the "success" key.
 */
final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::normalise($data),
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * @param  array<string, mixed>|null  $errors  field => messages, or any structured detail
     * @param  array<string, string>  $headers
     */
    public static function error(string $message, int $status = 400, ?array $errors = null, array $headers = []): JsonResponse
    {
        $body = ['success' => false, 'message' => $message];

        if ($errors !== null && $errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, $headers);
    }

    private static function normalise(mixed $data): mixed
    {
        // Resources are resolved to plain arrays so they sit under "data" instead of adding their own wrapper.
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->resolve(request());
        }

        return $data;
    }
}
