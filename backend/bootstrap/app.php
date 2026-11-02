<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureRole;
use App\Support\ApiRateLimit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The API never redirects to a login page. Without this, an /api request that lacks "Accept: application/json"
        // makes the auth middleware try to build a redirect to a "login" route this app does not have, and fail with a 500
        // instead of a 401. Paths outside /api keep the framework default.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));

        // Rate limit every /api route (limits: App\Support\ApiRateLimit, numbers: config/api.php).
        $middleware->throttleApi(ApiRateLimit::NAME);

        // Laravel's default order runs authentication BEFORE the throttle, so a flood of missing or invalid tokens would
        // be answered 401 without ever being counted. This is the framework's default priority list (Kernel::$middlewarePriority)
        // with ThrottleRequests moved ahead of authentication. RateLimitTest pins the resulting order.
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        // Explicit role check, e.g. ->middleware(['auth:supabase', 'role:admin']). See App\Http\Middleware\EnsureRole.
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every API error uses the same JSON envelope (see App\Support\ApiResponse).
        ApiExceptionHandler::register($exceptions);
    })->create();
