<?php

namespace App\Services\AI\Embeddings;

/**
 * Google answered 2xx but the answer is not usable. $reason says how, without any content:
 * not_json, missing_embedding, not_a_list, dimension_mismatch, non_numeric_value, non_finite_value, zero_vector,
 * count_mismatch.
 */
final class EmbeddingInvalidResponse extends EmbeddingException
{
    public function __construct(string $reason = 'invalid_response')
    {
        parent::__construct('The embedding service returned an unusable response.', $reason);
    }
}
