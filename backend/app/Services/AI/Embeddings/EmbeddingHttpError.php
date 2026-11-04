<?php

namespace App\Services\AI\Embeddings;

/**
 * Google answered with a status that is not 2xx and not 429 (for example 400, 401, 403, 404, 500, 503). A redirect counts as
 * an error too: redirects are never followed, so the key is never sent anywhere else.
 *
 * $status is the HTTP status code. $googleStatus is Google's own error code (such as INVALID_ARGUMENT or PERMISSION_DENIED)
 * only when it is one of a short list of known codes, else null. The response body itself is never kept, because Google's
 * error text can quote the request.
 *
 * $reason is "api_key_rejected" when the key itself was refused (HTTP 401, or Google says the key is invalid or expired;
 * Google answers an invalid key with HTTP 400), "access_denied" for HTTP 403 (key not allowed to use this API or region),
 * otherwise "http_error".
 */
final class EmbeddingHttpError extends EmbeddingException
{
    public function __construct(
        public readonly int $status,
        string $reason = 'http_error',
        public readonly ?string $googleStatus = null,
    ) {
        parent::__construct('The embedding service returned an error.', $reason);
    }
}
