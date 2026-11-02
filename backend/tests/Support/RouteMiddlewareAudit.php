<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Looks at the middleware the router will REALLY run for each route (aliases and groups expanded, in order) and reports
 * mistakes in the role middleware, which would otherwise only show up as a 500 when somebody calls the route:
 *
 *  - role with no roles                     ->middleware('role')
 *  - a name that is not exactly a UserRole  role:Admin, role:superadmin, role:" admin", role:admin,,lawyer
 *  - the same role twice                    role:admin,admin
 *  - role without the supabase guard before it (auth:supabase must come first)
 *
 * Also checks that the "role" alias still points at EnsureRole.
 */
final class RouteMiddlewareAudit
{
    /** @param  iterable<Route>  $routes
     * @return list<string> human-readable problems; empty means all good */
    public static function problems(Router $router, iterable $routes): array
    {
        $problems = [];

        if (($router->getMiddleware()['role'] ?? null) !== EnsureRole::class) {
            $problems[] = 'The "role" middleware alias does not point at '.EnsureRole::class.'.';
        }

        foreach ($routes as $route) {
            $where = implode('|', $route->methods()).' /'.ltrim($route->uri(), '/');
            $authenticatedWithSupabase = false;

            foreach (array_filter($router->gatherRouteMiddleware($route), 'is_string') as $middleware) {
                [$class, $arguments] = array_pad(explode(':', $middleware, 2), 2, null);

                if (self::isAuthenticate($class) && in_array('supabase', array_map('trim', explode(',', (string) $arguments)), true)) {
                    $authenticatedWithSupabase = true;
                }

                if ($class !== EnsureRole::class) {
                    continue;
                }

                if (! $authenticatedWithSupabase) {
                    $problems[] = "{$where}: role middleware without auth:supabase before it.";
                }

                if ($arguments === null || $arguments === '') {
                    $problems[] = "{$where}: role middleware without any role.";

                    continue;
                }

                $names = explode(',', $arguments);

                foreach ($names as $name) {
                    if (UserRole::tryFrom($name) === null) {
                        $problems[] = "{$where}: \"{$name}\" is not a role (exactly user, lawyer or admin).";
                    }
                }

                if (count($names) !== count(array_unique($names))) {
                    $problems[] = "{$where}: the same role is listed twice in \"{$arguments}\".";
                }
            }
        }

        return $problems;
    }

    private static function isAuthenticate(string $class): bool
    {
        return $class === Authenticate::class;
    }
}
