<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Explicit role check for routes, e.g.  ->middleware(['auth:supabase', 'role:admin'])
 *                                       ->middleware(['auth:supabase', 'role:lawyer,admin'])
 *
 *  - The roles must be listed explicitly. There is no hierarchy: "admin" does not satisfy "role:lawyer".
 *  - The role is the VERIFIED Supabase role of this request (app_metadata.role), never request input.
 *  - Not authenticated -> 401. Authenticated but the role is not listed -> 403.
 *  - A route with no roles, or with a role name that does not exist, is a programming error: it throws a
 *    LogicException (a generic 500) instead of silently allowing or denying everyone.
 *
 * It asks the "supabase" guard itself, so it also works without "auth:supabase" before it.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = $this->allowedRoles($roles);

        $user = Auth::guard('supabase')->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.', ['supabase']);
        }

        if (! $user instanceof User || ! $user->hasRole(...$allowed)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }

    /**
     * @param  list<string>  $roles
     * @return list<UserRole>
     */
    private function allowedRoles(array $roles): array
    {
        if ($roles === []) {
            throw new LogicException('The role middleware needs at least one role, e.g. role:admin.');
        }

        return array_map(function (string $name): UserRole {
            return UserRole::tryFrom($name)
                ?? throw new LogicException("Unknown role \"{$name}\" in the role middleware.");
        }, $roles);
    }
}
