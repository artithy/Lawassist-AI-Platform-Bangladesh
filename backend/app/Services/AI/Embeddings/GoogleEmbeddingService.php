<?php

namespace App\Services\AI\Embeddings;

use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Makes embedding vectors with Google's Gemini API (model gemini-embedding-001).
 *
 *     $vector = app(EmbeddingService::class)->embedQuery('চুরির শাস্তি কী?');   // list of 1536 floats, length 1
 *
 * Calls Google's documented REST endpoints:
 *     POST {base_url}/models/{model}:embedContent         one text
 *     POST {base_url}/models/{model}:batchEmbedContents   several texts
 * with the taskType Google documents for search: RETRIEVAL_DOCUMENT for stored texts, RETRIEVAL_QUERY for questions, and
 * outputDimensionality set from configuration, so the size never depends on Google's default (3072).
 *
 * Every vector is checked (right count, only finite numbers, not all zero) and scaled to length 1 before it is returned.
 * Anything else becomes one of the EmbeddingException subclasses with a fixed, safe message.
 *
 * Secrets and privacy:
 *  - The API key is read only through GoogleAiConfig (config/google_ai.php) and sent only in the "x-goog-api-key" header,
 *    never in the address or the body. Redirects are switched off, so the header can not be passed on to another host.
 *  - The texts are legal material. This class never logs them or the key, never puts them (or the server address, the
 *    response body or the vector) into an exception, and does not retry (a retry would send the same text again).
 *  - The text parameters are marked #[SensitiveParameter] so PHP hides them in stack traces.
 *  - The texts are sent to Google. Whoever calls this decides what may leave the system.
 *
 * Not final on purpose: a final class can not be replaced by a test double in the tests of the code that uses it.
 */
class GoogleEmbeddingService implements EmbeddingService
{
    private const API_KEY_HEADER = 'x-goog-api-key';

    /**
     * A sanity limit on one text, in bytes (UTF-8, so a Bengali letter takes 3). Google's real limit is 2048 tokens, which
     * only Google can count; a text over that gets an HTTP 400. This limit just stops a whole book going out by mistake.
     */
    public const MAX_TEXT_BYTES = 60000;

    /** The only Google error codes that are copied into EmbeddingHttpError::$googleStatus. */
    private const GOOGLE_STATUSES = [
        'INVALID_ARGUMENT', 'FAILED_PRECONDITION', 'OUT_OF_RANGE', 'UNAUTHENTICATED', 'PERMISSION_DENIED', 'NOT_FOUND',
        'ALREADY_EXISTS', 'RESOURCE_EXHAUSTED', 'CANCELLED', 'INTERNAL', 'UNAVAILABLE', 'DEADLINE_EXCEEDED', 'UNIMPLEMENTED',
    ];

    /** Google error details that say the key itself is the problem. */
    private const KEY_REJECTED_REASONS = ['API_KEY_INVALID', 'API_KEY_EXPIRED'];

    public function model(): string
    {
        return GoogleAiConfig::model();
    }

    public function dimensions(): int
    {
        return GoogleAiConfig::dimensions();
    }

    public function embedDocument(#[SensitiveParameter] string $text): array
    {
        return $this->embedOne($text, EmbeddingTaskType::RetrievalDocument);
    }

    public function embedQuery(#[SensitiveParameter] string $text): array
    {
        return $this->embedOne($text, EmbeddingTaskType::RetrievalQuery);
    }

    public function embedDocuments(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        if (! array_is_list($texts)) {
            throw new InvalidArgumentException('Texts must be a list.');
        }

        foreach ($texts as $position => $text) {
            $this->checkText($text, "Text {$position}");
        }

        $model = GoogleAiConfig::model();
        $dimensions = GoogleAiConfig::dimensions();
        $vectors = [];

        foreach (array_chunk($texts, GoogleAiConfig::batchSize()) as $chunk) {
            $requests = [];

            foreach ($chunk as $text) {
                $requests[] = $this->requestFor($text, EmbeddingTaskType::RetrievalDocument, $model, $dimensions);
            }

            $response = $this->send('batchEmbedContents', ['requests' => $requests], $model);

            foreach ($this->batchVectors($response, count($chunk), $dimensions) as $vector) {
                $vectors[] = $vector;
            }
        }

        return $vectors;
    }

    /** @return list<float> */
    private function embedOne(#[SensitiveParameter] string $text, EmbeddingTaskType $type): array
    {
        $this->checkText($text, 'Text');

        $model = GoogleAiConfig::model();
        $dimensions = GoogleAiConfig::dimensions();

        $response = $this->send('embedContent', $this->requestFor($text, $type, $model, $dimensions), $model);

        return $this->singleVector($response, $dimensions);
    }

    /**
     * What is wrong is said without quoting the text: it is private.
     *
     * @throws InvalidArgumentException
     */
    private function checkText(#[SensitiveParameter] mixed $text, string $label): void
    {
        if (! is_string($text) || trim($text) === '') {
            throw new InvalidArgumentException("{$label} needs non-empty text.");
        }

        if (strlen($text) > self::MAX_TEXT_BYTES) {
            throw new InvalidArgumentException("{$label} is too long to embed in one piece.");
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException("{$label} must be valid UTF-8.");
        }
    }

    /** @return array<string, mixed> one EmbedContentRequest */
    private function requestFor(#[SensitiveParameter] string $text, EmbeddingTaskType $type, string $model, int $dimensions): array
    {
        return [
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => $text]]],
            'taskType' => $type->value,
            'outputDimensionality' => $dimensions,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws EmbeddingMisconfigured|EmbeddingConnectionFailed|EmbeddingTimedOut|EmbeddingRateLimited|EmbeddingHttpError
     */
    private function send(string $method, array $payload, string $model): Response
    {
        try {
            $response = Http::baseUrl(GoogleAiConfig::baseUrl())
                ->withHeaders([self::API_KEY_HEADER => GoogleAiConfig::apiKey()])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(GoogleAiConfig::connectTimeout())
                ->timeout(GoogleAiConfig::timeout())
                ->withoutRedirecting()
                ->post("/models/{$model}:{$method}", $payload);
        } catch (ConnectionException $e) {
            // Not chained as "previous" on purpose: its message carries the full request URL.
            throw $this->isTimeout($e) ? new EmbeddingTimedOut : new EmbeddingConnectionFailed;
        }

        if ($response->status() === 429) {
            throw new EmbeddingRateLimited($this->retryAfterSeconds($response));
        }

        if (! $response->successful()) {
            throw $this->httpError($response);
        }

        return $response;
    }

    /** @return list<float> */
    private function singleVector(Response $response, int $dimensions): array
    {
        $body = $this->jsonBody($response);
        $embedding = $body['embedding'] ?? null;

        if (! is_array($embedding)) {
            throw new EmbeddingInvalidResponse('missing_embedding');
        }

        return EmbeddingVector::normalized(EmbeddingVector::validated($embedding['values'] ?? null, $dimensions));
    }

    /** @return list<list<float>> */
    private function batchVectors(Response $response, int $expected, int $dimensions): array
    {
        $body = $this->jsonBody($response);
        $embeddings = $body['embeddings'] ?? null;

        if (! is_array($embeddings) || ! array_is_list($embeddings)) {
            throw new EmbeddingInvalidResponse('missing_embedding');
        }

        if (count($embeddings) !== $expected) {
            throw new EmbeddingInvalidResponse('count_mismatch');
        }

        $vectors = [];

        foreach ($embeddings as $embedding) {
            $values = is_array($embedding) ? ($embedding['values'] ?? null) : null;
            $vectors[] = EmbeddingVector::normalized(EmbeddingVector::validated($values, $dimensions));
        }

        return $vectors;
    }

    /** @return array<mixed> */
    private function jsonBody(Response $response): array
    {
        $body = $response->json(); // null when the body is not valid JSON

        if (! is_array($body)) {
            throw new EmbeddingInvalidResponse('not_json');
        }

        return $body;
    }

    /**
     * Laravel throws the same ConnectionException for "could not connect" and for "took too long". Guzzle names the second
     * kind: ConnectTimeoutException (the connection did not open in time) and NetworkTimeoutException (no answer in time).
     * cURL error 28 is "operation timed out"; it is also checked in the message as a fallback for other transports.
     */
    private function isTimeout(ConnectionException $e): bool
    {
        $previous = $e->getPrevious();

        if ($previous instanceof ConnectTimeoutException || $previous instanceof NetworkTimeoutException) {
            return true;
        }

        return preg_match('/cURL error 28\b/', $e->getMessage()) === 1;
    }

    /** Retry-After in whole seconds (0 to one day), or null when absent, a date, or anything else. */
    private function retryAfterSeconds(Response $response): ?int
    {
        $header = trim($response->header('Retry-After'));

        if (preg_match('/\A[0-9]{1,6}\z/', $header) !== 1) {
            return null;
        }

        return (int) $header <= 86400 ? (int) $header : null;
    }

    /** Only whitelisted codes are read from Google's error body; its free text is never used. */
    private function httpError(Response $response): EmbeddingHttpError
    {
        $status = $response->status();
        $error = $response->json('error');
        $error = is_array($error) ? $error : [];

        $googleStatus = is_string($error['status'] ?? null) && in_array($error['status'], self::GOOGLE_STATUSES, true)
            ? $error['status']
            : null;

        $keyRejected = $status === 401;

        foreach (is_array($error['details'] ?? null) ? $error['details'] : [] as $detail) {
            if (is_array($detail) && is_string($detail['reason'] ?? null) && in_array($detail['reason'], self::KEY_REJECTED_REASONS, true)) {
                $keyRejected = true;
            }
        }

        $reason = $keyRejected ? 'api_key_rejected' : ($status === 403 ? 'access_denied' : 'http_error');

        return new EmbeddingHttpError($status, $reason, $googleStatus);
    }
}
