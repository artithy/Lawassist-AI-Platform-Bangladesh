<?php

/*
|--------------------------------------------------------------------------
| Google AI (Gemini API)
|--------------------------------------------------------------------------
|
| Used by App\Services\AI\Embeddings\GoogleEmbeddingService to turn text into embedding vectors with gemini-embedding-001.
|
| api_key               GOOGLE_AI_API_KEY. A SECRET. Put it only in your private backend/.env (which is not committed) and
|                       never in this file, .env.example, a test, a log or a chat. It has no default on purpose: without it the
|                       service refuses to run (EmbeddingMisconfigured) instead of guessing. Remember that `php artisan
|                       config:show google_ai` and tinker print it, and so does a config dump in an error report.
| base_url              The Gemini API address. Must be https (plain http is accepted only for localhost, for local test
|                       servers). The key travels in a request header, never in the address.
| embedding_model       The bare model name, without a "models/" prefix.
| embedding_dimensions  How many numbers each vector has, 128 to 3072. 1536 is one of the sizes Google recommends. Vectors
|                       stored in the database must all have the same size, so changing this later means re-embedding
|                       everything. A value that is not a whole number in that range is refused, never "fixed" silently.
| connect_timeout       Seconds to wait for the connection to open.
| timeout               Seconds to wait for the whole request.
| batch_size            How many texts go into one batch request (1 to 100; anything else uses the default).
|
| Values are validated when they are used (App\Services\AI\Embeddings\GoogleAiConfig). Only this file reads the
| environment; the rest of the application reads settings through config().
|
*/

return [

    'api_key' => env('GOOGLE_AI_API_KEY'),

    'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    'embedding_model' => env('GOOGLE_AI_EMBEDDING_MODEL', 'gemini-embedding-001'),

    'embedding_dimensions' => env('GOOGLE_AI_EMBEDDING_DIMENSIONS', 1536),

    'connect_timeout' => env('GOOGLE_AI_CONNECT_TIMEOUT', 5),

    'timeout' => env('GOOGLE_AI_TIMEOUT', 30),

    'batch_size' => env('GOOGLE_AI_EMBEDDING_BATCH_SIZE', 50),

];
