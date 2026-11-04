<?php

namespace App\Services\AI;

/** Ollama accepted the request but did not finish answering within the configured timeout. */
final class OllamaTimedOut extends OllamaException
{
    public function __construct(string $reason = 'timed_out')
    {
        parent::__construct('The AI service did not answer in time.', $reason);
    }
}
