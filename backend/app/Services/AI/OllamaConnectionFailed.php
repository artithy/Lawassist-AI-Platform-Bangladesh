<?php

namespace App\Services\AI;

/** Ollama could not be reached at all: not running, wrong host or port, name not resolved, connection refused. */
final class OllamaConnectionFailed extends OllamaException
{
    public function __construct(string $reason = 'connection_failed')
    {
        parent::__construct('The AI service could not be reached.', $reason);
    }
}
