<?php

namespace App\Services\AI\Embeddings;

/**
 * Google answered 429: the request rate or the quota of the API key is used up. Nothing was embedded.
 *
 * $retryAfterSeconds is Google's Retry-After hint when it sent a sensible one (0 to one day), else null. The service itself
 * never retries (a retry sends the same text again); a queued job that ingests many texts should wait and try again.
 */
final class EmbeddingRateLimited extends EmbeddingException
{
    public function __construct(public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct('The embedding service is busy. Try again later.', 'rate_limited');
    }
}
