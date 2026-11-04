<?php

namespace App\Services\AI\Embeddings;

use RuntimeException;

/**
 * Base class of everything the embedding service can throw because of Google or its configuration.
 *
 *   EmbeddingMisconfigured     config/google_ai.php holds an unusable value, or the API key is missing (nothing was sent)
 *   EmbeddingConnectionFailed  Google could not be reached (no network, DNS, TLS, connection refused)
 *   EmbeddingTimedOut          Google did not answer within the configured time
 *   EmbeddingRateLimited       Google answered 429 (quota or rate limit); carries the Retry-After hint when there is one
 *   EmbeddingHttpError         Google answered with another non-success status (carries the status code)
 *   EmbeddingInvalidResponse   Google answered 2xx but not with usable vectors (wrong shape, size or numbers)
 *
 * The message is a fixed, safe sentence. It never contains the API key, the text that was embedded, the server address,
 * the response body or the original exception text, so it is safe to log and to show as a generic error. $reason is a short
 * machine code (for logs and tests) that is just as free of content. The original low-level exception is deliberately NOT
 * chained as "previous": Guzzle's messages contain the full request URL.
 */
abstract class EmbeddingException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
