<?php

namespace App\Services\AI;

/** Ollama answered with a success status, but the body is not a usable chat answer. $reason says which check failed. */
final class OllamaInvalidResponse extends OllamaException
{
    public function __construct(string $reason = 'invalid_response')
    {
        parent::__construct('The AI service returned an unusable response.', $reason);
    }
}
