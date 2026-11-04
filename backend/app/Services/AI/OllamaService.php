<?php

namespace App\Services\AI;

use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Talks to Ollama's chat API (POST {base_url}/api/chat) and returns the assistant's answer as text.
 *
 *     $answer = app(OllamaService::class)->chat([
 *         ['role' => 'system', 'content' => 'You are a careful legal assistant.'],
 *         ['role' => 'user', 'content' => 'What is a power of attorney?'],
 *     ]);
 *
 * Deliberately small and independent of the rest of the app: it knows nothing about controllers, users, requests or the
 * frontend. Settings come only from config/ollama.php (via OllamaConfig); nothing here reads the environment.
 *
 * Every request is a single, non-streaming call (stream = false), with the configured model and timeouts, and with
 * redirects switched off. Failures become one of the OllamaException subclasses with a fixed, safe message.
 *
 * Privacy: the messages are legal text. This class never logs them, never puts them (or the answer, the server address or
 * the response body) into an exception, and does not retry (a retry would send the same text again). Whoever calls it
 * decides what to log, and should log reason codes only.
 *
 * Not final on purpose: a final class can not be replaced by a test double in the tests of the code that uses it.
 */
class OllamaService
{
    private const ROLES = ['system', 'user', 'assistant'];

    private const CHAT_PATH = '/api/chat';

    /**
     * Sends the conversation and returns the assistant's reply text.
     *
     * @param  list<array{role: string, content: string}>  $messages  Ollama's message format. Only "role" (system, user or
     *                                                                assistant) and "content" are forwarded; anything else
     *                                                                in a message is dropped.
     *
     * @throws InvalidArgumentException when $messages itself is unusable (a bug in the caller; nothing is sent)
     * @throws OllamaMisconfigured when config/ollama.php has an unusable base URL or model
     * @throws OllamaConnectionFailed when Ollama can not be reached
     * @throws OllamaTimedOut when Ollama does not answer in time
     * @throws OllamaHttpError when Ollama answers with a non-success status
     * @throws OllamaInvalidResponse when the answer is not a usable chat reply
     */
    public function chat(array $messages): string
    {
        $payload = [
            'model' => OllamaConfig::model(),
            'messages' => $this->validated($messages),
            'stream' => false,
        ];

        try {
            $response = Http::baseUrl(OllamaConfig::baseUrl())
                ->acceptJson()
                ->asJson()
                ->connectTimeout(OllamaConfig::connectTimeout())
                ->timeout(OllamaConfig::timeout())
                ->withoutRedirecting()
                ->post(self::CHAT_PATH, $payload);
        } catch (ConnectionException $e) {
            // Not chained as "previous" on purpose: its message carries the full request URL.
            throw $this->isTimeout($e) ? new OllamaTimedOut : new OllamaConnectionFailed;
        }

        if (! $response->successful()) {
            throw new OllamaHttpError($response->status());
        }

        return $this->answerFrom($response);
    }

    /**
     * @param  array<mixed>  $messages
     * @return list<array{role: string, content: string}>
     */
    private function validated(array $messages): array
    {
        if ($messages === [] || ! array_is_list($messages)) {
            throw new InvalidArgumentException('Messages must be a non-empty list.');
        }

        $clean = [];

        foreach ($messages as $position => $message) {
            $role = is_array($message) ? ($message['role'] ?? null) : null;
            $content = is_array($message) ? ($message['content'] ?? null) : null;

            // The messages are never echoed in these texts: they are private.
            if (! is_string($role) || ! in_array($role, self::ROLES, true)) {
                throw new InvalidArgumentException("Message {$position} needs a role of system, user or assistant.");
            }

            if (! is_string($content) || trim($content) === '') {
                throw new InvalidArgumentException("Message {$position} needs non-empty text content.");
            }

            $clean[] = ['role' => $role, 'content' => $content];
        }

        return $clean;
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

    /** Pulls message.content out of the response, after checking the shape Ollama documents for stream = false. */
    private function answerFrom(Response $response): string
    {
        $body = $response->json(); // null when the body is not valid JSON

        if (! is_array($body)) {
            throw new OllamaInvalidResponse('not_json');
        }

        $message = $body['message'] ?? null;

        if (! is_array($message)) {
            throw new OllamaInvalidResponse('missing_message');
        }

        if (($message['role'] ?? null) !== 'assistant') {
            throw new OllamaInvalidResponse('unexpected_role');
        }

        $content = $message['content'] ?? null;

        if (! is_string($content)) {
            throw new OllamaInvalidResponse('missing_content');
        }

        // A non-streaming answer is always complete; "done": false would mean a partial one.
        if (($body['done'] ?? true) !== true) {
            throw new OllamaInvalidResponse('incomplete');
        }

        if (trim($content) === '') {
            throw new OllamaInvalidResponse('empty_content');
        }

        return $content;
    }
}
