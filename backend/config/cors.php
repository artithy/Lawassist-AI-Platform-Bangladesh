<?php

use App\Support\FrontendOrigins;

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| The React frontend runs on another origin and calls this API from the browser, sending the Supabase access token in
| the Authorization header. Only that frontend is allowed: FRONTEND_ORIGIN holds its origin, or several comma-separated
| origins. There is no wildcard. If FRONTEND_ORIGIN is not set, only the local dev server (http://localhost:5173) is
| allowed, and only when APP_ENV is local or testing: anywhere else, including production, nothing is allowed until
| FRONTEND_ORIGIN is set. Authentication is by bearer token, not cookies, so credentials are not enabled.
|
| Retry-After (503/429) and WWW-Authenticate (401) are exposed so the frontend can read them on cross-origin responses.
|
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

    'allowed_origins' => FrontendOrigins::resolve(env('FRONTEND_ORIGIN'), env('APP_ENV', 'production')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],

    'exposed_headers' => ['Retry-After', 'WWW-Authenticate'],

    'max_age' => 600,

    'supports_credentials' => false,

];
