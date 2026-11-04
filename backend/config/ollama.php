<?php

/*
|--------------------------------------------------------------------------
| Ollama (local language-model server)
|--------------------------------------------------------------------------
|
| Used by App\Services\AI\OllamaService. Laravel runs inside Docker while Ollama runs on the Mac host, which Docker
| Desktop exposes to containers as host.docker.internal. If you run Laravel directly on the host instead, set
| OLLAMA_BASE_URL=http://localhost:11434.
|
| The values are validated when they are used (App\Services\AI\OllamaConfig): a base URL or model that is empty or
| malformed is refused, and a timeout that is missing, zero, negative or not a number falls back to the default shown
| here, so a typo can never mean "wait forever".
|
| connect_timeout  seconds to wait for the connection to Ollama to open.
| timeout          seconds to wait for the whole request. Language models are slow, and a "-cloud" model also has to reach
|                  the internet, so this is generous.
|
*/

return [

    'base_url' => env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'),

    'model' => env('OLLAMA_MODEL', 'gemma4:31b-cloud'),

    'connect_timeout' => env('OLLAMA_CONNECT_TIMEOUT', 5),

    'timeout' => env('OLLAMA_TIMEOUT', 120),

];
