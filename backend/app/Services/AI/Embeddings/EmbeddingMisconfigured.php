<?php

namespace App\Services\AI\Embeddings;

/**
 * config/google_ai.php has a value that can not be used (or the API key is missing). Nothing was sent to Google.
 * $reason names the setting only (missing_api_key, invalid_api_key, invalid_base_url, invalid_model, invalid_dimensions);
 * it never contains the value.
 */
final class EmbeddingMisconfigured extends EmbeddingException
{
    public function __construct(string $reason = 'invalid_configuration')
    {
        parent::__construct('The embedding service is not configured correctly.', $reason);
    }
}
