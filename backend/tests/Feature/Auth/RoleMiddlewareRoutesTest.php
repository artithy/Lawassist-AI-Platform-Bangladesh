<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRole;
use Illuminate\Routing\Route as RouteObject;
use Illuminate\Support\Facades\Route;
use Tests\Support\RouteMiddlewareAudit;
use Tests\TestCase;

/**
 * A typo in a route's role middleware ("role:Admin", "role:superadmin", a missing argument) is a 500 at request time and
 * is easy to miss. These tests read every REGISTERED route and fail the build instead.
 *
 * Part 1 audits the real application routes. Part 2 proves the audit itself notices each kind of mistake (an audit that
 * can not fail is worthless).
 */
class RoleMiddlewareRoutesTest extends TestCase
{
    /** Routes under /api that anyone may call without a token. Adding a route here is a security decision. */
    private const PUBLIC_API_ROUTES = ['api/health'];

    // ---- Part 1: the real routes -------------------------------------------------------------------------------

    public function test_every_registered_role_middleware_is_valid(): void
    {
        $problems = RouteMiddlewareAudit::problems(app('router'), Route::getRoutes()->getRoutes());

        $this->assertSame([], $problems, "Invalid role middleware:\n - ".implode("\n - ", $problems));
    }

    public function test_the_role_alias_points_at_the_role_middleware(): void
    {
        $this->assertSame(EnsureRole::class, app('router')->getMiddleware()['role']);
    }

    public function test_every_api_route_requires_the_supabase_guard_unless_it_is_explicitly_public(): void
    {
        $unprotected = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || in_array($route->uri(), self::PUBLIC_API_ROUTES, true)) {
                continue;
            }

            if (! in_array('auth:supabase', $route->gatherMiddleware(), true)) {
                $unprotected[] = implode('|', $route->methods()).' /'.$route->uri();
            }
        }

        $this->assertSame([], $unprotected, 'API routes without auth:supabase: '.implode(', ', $unprotected));
    }

    public function test_the_public_api_routes_list_only_routes_that_exist(): void
    {
        $uris = array_map(fn (RouteObject $route) => $route->uri(), Route::getRoutes()->getRoutes());

        foreach (self::PUBLIC_API_ROUTES as $public) {
            $this->assertContains($public, $uris);
        }
    }

    public function test_no_route_uses_sanctum_or_another_guard_for_the_api(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'auth:')) {
                    $this->assertSame('auth:supabase', $middleware, $route->uri());
                }
                $this->assertStringNotContainsString('sanctum', (string) $middleware, $route->uri());
            }
        }
    }

    // ---- Part 2: the audit catches each mistake ------------------------------------------------------------------

    /** @param  list<string>|string  $middleware */
    private function auditOf(array|string $middleware): array
    {
        $route = Route::middleware($middleware)->get('api/_audit/'.uniqid(), fn () => 'ok');

        return RouteMiddlewareAudit::problems(app('router'), [$route]);
    }

    public function test_valid_registrations_pass(): void
    {
        $this->assertSame([], $this->auditOf(['auth:supabase', 'role:admin']));
        $this->assertSame([], $this->auditOf(['auth:supabase', 'role:lawyer,admin']));
        $this->assertSame([], $this->auditOf(['auth:supabase', 'role:user,lawyer,admin']));
        $this->assertSame([], $this->auditOf(['auth:supabase'])); // no role middleware at all is fine
    }

    public function test_every_supported_role_is_accepted_on_its_own(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame([], $this->auditOf(['auth:supabase', 'role:'.$role->value]), $role->value);
        }
    }

    public function test_an_unknown_role_name_is_reported(): void
    {
        foreach (['superadmin', 'banned', 'administrator', 'moderator'] as $name) {
            $problems = $this->auditOf(['auth:supabase', 'role:'.$name]);

            $this->assertCount(1, $problems, $name);
            $this->assertStringContainsString("\"{$name}\" is not a role", $problems[0]);
        }
    }

    public function test_wrong_capitalisation_is_reported(): void
    {
        foreach (['Admin', 'ADMIN', 'Lawyer', 'User'] as $name) {
            $this->assertCount(1, $this->auditOf(['auth:supabase', 'role:'.$name]), $name);
        }
    }

    public function test_padding_and_empty_entries_are_reported(): void
    {
        $this->assertNotEmpty($this->auditOf(['auth:supabase', 'role: admin']));
        $this->assertNotEmpty($this->auditOf(['auth:supabase', 'role:admin ']));
        $this->assertNotEmpty($this->auditOf(['auth:supabase', 'role:admin,,lawyer']));
        $this->assertNotEmpty($this->auditOf(['auth:supabase', 'role:admin,']));
        $this->assertNotEmpty($this->auditOf(['auth:supabase', 'role:,admin']));
    }

    public function test_a_role_middleware_without_any_role_is_reported(): void
    {
        $problems = $this->auditOf(['auth:supabase', 'role']);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('without any role', $problems[0]);
        $this->assertNotEmpty($this->auditOf(['auth:supabase', 'role:']));
    }

    public function test_a_duplicated_role_is_reported(): void
    {
        $problems = $this->auditOf(['auth:supabase', 'role:admin,admin']);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('twice', $problems[0]);
    }

    public function test_a_role_middleware_without_the_supabase_guard_is_reported(): void
    {
        foreach ([['role:admin'], ['auth:web', 'role:admin'], ['auth', 'role:admin']] as $middleware) {
            $problems = $this->auditOf($middleware);

            $this->assertNotEmpty($problems, implode(',', $middleware));
            $this->assertStringContainsString('without auth:supabase', $problems[0]);
        }
    }

    public function test_the_role_middleware_listed_before_the_guard_is_reported(): void
    {
        // The router keeps the order as written, so this route would run the role check first.
        $problems = $this->auditOf(['role:admin', 'auth:supabase']);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('without auth:supabase before it', $problems[0]);
    }

    public function test_several_mistakes_on_one_route_are_all_reported(): void
    {
        $problems = $this->auditOf(['role:Admin,Admin']); // no guard, wrong case, duplicated

        $this->assertGreaterThanOrEqual(3, count($problems));
    }

    public function test_a_wrong_alias_is_reported(): void
    {
        $router = app('router');
        $router->aliasMiddleware('role', \stdClass::class); // simulate someone re-pointing the alias

        $problems = RouteMiddlewareAudit::problems($router, []);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('alias', $problems[0]);
    }

    public function test_the_audit_looks_at_the_expanded_middleware_not_just_the_spelling(): void
    {
        // The same mistake inside a middleware GROUP must be found too.
        app('router')->middlewareGroup('admin-area', ['auth:supabase', 'role:superadmin']);
        $route = Route::middleware('admin-area')->get('api/_audit/group', fn () => 'ok');

        $problems = RouteMiddlewareAudit::problems(app('router'), [$route]);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('"superadmin" is not a role', $problems[0]);
    }
}
