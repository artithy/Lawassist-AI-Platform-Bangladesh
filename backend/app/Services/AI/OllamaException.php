<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Base class of everything OllamaService can throw because of Ollama or its configuration.
 *
 * Callers can catch this one type, or a specific subclass:
 *   OllamaMisconfigured     config/ollama.php holds an unusable value (nothing was sent)
 *   OllamaConnectionFailed  the server could not be reached (not running, wrong host or port, DNS)
 *   OllamaTimedOut          the server did not answer within the configured time
 *   OllamaHttpError         the server answered with a non-success status (carries the status code)
 *   OllamaInvalidResponse   the server answered 2xx but not with a usable chat answer
 *
 * The message is a fixed, safe sentence. It never contains the prompt, the model's answer, the server address, the
 * response body or the original exception text, so it is safe to log and to show as a generic error. $reason is a short
 * machine code (for logs and tests) that is just as free of content. The original low-level exception is deliberately NOT
 * chained as "previous": Guzzle's messages contain the full request URL.
 */
abstract class OllamaException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
