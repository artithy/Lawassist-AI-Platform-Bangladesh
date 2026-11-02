<?php

namespace App\Providers;

use App\Auth\Supabase\SupabaseGuard;
use App\Auth\Supabase\SupabaseRequestAuthenticator;
use App\Support\ApiRateLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The "supabase" guard (config/auth.php): authenticates API requests from a verified Supabase access token.
        // Sanctum is not used. SupabaseGuard (not Auth::viaRequest) so that no user is kept from one request to the next.
        Auth::extend('supabase', function ($app, string $name, array $config) {
            $guard = new SupabaseGuard(
                fn (Request $request) => $app->make(SupabaseRequestAuthenticator::class)($request),
                $app['request'],
                Auth::createUserProvider($config['provider'] ?? null),
            );

            $app->refresh('request', $guard, 'setRequest'); // follow the request the framework is handling now

            return $guard;
        });

        // Rate limits for every /api route (applied in bootstrap/app.php, configured in config/api.php).
        ApiRateLimit::register();
    }
}
