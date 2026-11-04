<?php

namespace App\Services\AI\Embeddings;

/** Google accepted the request but did not finish answering within the configured timeout. */
final class EmbeddingTimedOut extends EmbeddingException
{
    public function __construct(string $reason = 'timed_out')
    {
        parent::__construct('The embedding service did not answer in time.', $reason);
    }
}
