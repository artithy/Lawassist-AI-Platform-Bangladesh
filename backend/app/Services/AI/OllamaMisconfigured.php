<?php

namespace App\Services\AI;

/** config/ollama.php has a base URL or model that can not be used. Nothing was sent to Ollama. */
final class OllamaMisconfigured extends OllamaException
{
    public function __construct(string $reason = 'invalid_configuration')
    {
        parent::__construct('The AI service is not configured correctly.', $reason);
    }
}
