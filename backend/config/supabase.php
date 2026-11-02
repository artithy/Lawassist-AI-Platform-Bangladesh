<?php

/*
|--------------------------------------------------------------------------
| Supabase Auth (JWT verification)
|--------------------------------------------------------------------------
|
| The backend never trusts a token it has not verified. Access tokens are signed by Supabase with an ES256
| (ECC P-256) key; the matching PUBLIC keys are published at <SUPABASE_URL>/auth/v1/.well-known/jwks.json.
|
| Only variable NAMES live here. Values belong in backend/.env, which must never be committed or printed.
|
*/

return [

    // Your project URL, e.g. https://<project-ref>.supabase.co  (the same value the frontend uses). Required.
    'url' => env('SUPABASE_URL'),

    // Must match the "aud" claim of a signed-in user's access token.
    'audience' => env('SUPABASE_JWT_AUDIENCE', 'authenticated'),

    // Optional override of where the public keys are fetched from. Leave empty to use <url>/auth/v1/.well-known/jwks.json.
    // Must be https.
    'jwks_url' => env('SUPABASE_JWKS_URL'),

    // Seconds of clock difference tolerated for exp / nbf / iat.
    'leeway' => 30,

    // Longest Authorization token we will even look at.
    'max_token_bytes' => 8192,

    'jwks' => [
        // Which cache store (a name from config/cache.php) holds the public keys and the timing markers.
        // It is chosen explicitly so the keys never depend on Laravel's default store: whoever can write to this
        // store can plant a signing key, so it must be private to the server.
        //   file   (default) local to this server. Right choice for development and for one or a few servers.
        //   redis  fine for production if the Redis instance is private.
        //   database  NOT recommended: that table sits in the Supabase "public" schema and is reachable through
        //             Supabase's Data API unless row-level security is on.
        //   array  tests only: it keeps nothing between requests, so every request would refetch the keys.
        'cache_store' => env('SUPABASE_JWKS_CACHE_STORE', 'file'),

        'cache_ttl' => 600,        // keys are reused for 10 minutes
        'stale_ttl' => 3600,       // if Supabase cannot be reached, the last good keys are used for up to 1 hour
        'refetch_interval' => 60,  // an unknown key id can trigger a refetch at most once per this many seconds
        'failure_backoff' => 15,   // after a failed fetch, do not retry for this many seconds
        'timeout' => 3,
        'connect_timeout' => 2,
    ],

    /*
    | TRANSITION ONLY: tokens issued before the move to ES256 signing keys were signed with the legacy HS256 shared
    | secret and stay valid until they expire (Supabase default: 1 hour). While that secret is set, HS256 tokens are
    | accepted in addition to ES256.
    |
    |   - secret:       the legacy JWT secret, read from the environment only. Leave EMPTY to turn HS256 off completely.
    |   - accept_until: optional ISO-8601 date/time after which HS256 is rejected even if the secret is still set
    |                   (e.g. 2026-11-02T12:00:00Z). An unreadable value turns HS256 off (fails closed).
    |
    | Once old tokens have expired, empty both and delete the secret from .env.
    */
    'legacy_hs256' => [
        'secret' => env('SUPABASE_LEGACY_JWT_SECRET'),
        'accept_until' => env('SUPABASE_LEGACY_HS256_UNTIL'),
    ],

];
