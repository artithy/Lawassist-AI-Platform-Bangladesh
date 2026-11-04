<?php

namespace App\Services\AI\Embeddings;

/** Google could not be reached at all: no network, name not resolved, TLS failure, connection refused. */
final class EmbeddingConnectionFailed extends EmbeddingException
{
    public function __construct(string $reason = 'connection_failed')
    {
        parent::__construct('The embedding service could not be reached.', $reason);
    }
}
