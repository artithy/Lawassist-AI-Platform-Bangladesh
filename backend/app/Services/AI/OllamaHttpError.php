<?php

namespace App\Services\AI;

/**
 * Ollama answered with a status that is not 2xx (for example 404 "model not found", 500, 503). A redirect counts as an
 * error too: redirects are never followed. $status is the HTTP status code; the response body is never kept.
 */
final class OllamaHttpError extends OllamaException
{
    public function __construct(public readonly int $status)
    {
        parent::__construct('The AI service returned an error.', 'http_error');
    }
}
