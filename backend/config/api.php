<?php

/*
|--------------------------------------------------------------------------
| API rate limits (requests per minute)
|--------------------------------------------------------------------------
|
| Every /api route is limited twice (see App\Support\ApiRateLimit):
|   per_ip     all requests from one client address, authenticated or not (this is what slows down anonymous floods);
|   per_token  all requests carrying the same Authorization header (one signed-in client), so one noisy user behind a
|              shared address (office, school, mobile carrier) does not use up everybody else's allowance.
|
| Development environments (local, testing) get ten times the allowance so that hot reloads, React StrictMode double
| requests and test suites never hit the limit. Override with API_RATE_LIMIT_PER_IP / API_RATE_LIMIT_PER_TOKEN. A value
| that is missing, zero, negative or not a number falls back to the default: the limit can not be switched off by typo.
|
*/

$development = in_array(strtolower((string) env('APP_ENV', 'production')), ['local', 'testing'], true);

return [

    'rate_limit' => [
        'per_ip' => env('API_RATE_LIMIT_PER_IP', $development ? 3000 : 300),
        'per_token' => env('API_RATE_LIMIT_PER_TOKEN', $development ? 1200 : 120),
    ],

];
