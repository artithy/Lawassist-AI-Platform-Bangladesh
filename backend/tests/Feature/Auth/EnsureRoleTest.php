<?php

namespace Tests\Feature\Auth;

use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\ConfiguresSupabase;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

/**
 * The "role" middleware: explicit roles, no hierarchy, 401 when not authenticated, 403 when the verified role is not
 * listed. Throw-away routes (test only) use it; tokens are real signed tokens as in SupabaseGuardTest.
 */
class EnsureRoleTest extends TestCase
{
    use ConfiguresSupabase;
    use RefreshDatabase;

    private const FORBIDDEN = ['success' => false, 'message' => 'You do not have permission to perform this action.'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();

        $ok = fn () => ApiResponse::success(['reached' => true]);

        Route::middleware(['auth:supabase', 'role:admin'])->get('api/_test/admin-only', $ok);
        Route::middleware(['auth:supabase', 'role:lawyer'])->get('api/_test/lawyer-only', $ok);
        Route::middleware(['auth:supabase', 'role:user'])->get('api/_test/user-only', $ok);
        Route::middleware(['auth:supabase', 'role:lawyer,admin'])->get('api/_test/lawyer-or-admin', $ok);
        Route::middleware(['role:admin'])->get('api/_test/role-without-auth-middleware', $ok);
        Route::middleware(['auth:supabase', 'role:superadmin'])->get('api/_test/unknown-role', $ok);
        Route::middleware(['auth:supabase', 'role:Admin'])->get('api/_test/wrong-case-role', $ok);
        Route::middleware(['auth:supabase', 'role'])->get('api/_test/no-roles', $ok);
    }

    private function tokenWithRole(?string $role, array $extra = []): string
    {
        $sub = (string) Str::uuid();
        $appMetadata = $role === null ? ['provider' => 'google'] : ['role' => $role];

        return $this->keys->sign(SupabaseTestKeys::claims(array_replace([
            'sub' => $sub,
            'email' => "u{$sub}@example.com",
            'app_metadata' => $appMetadata,
        ], $extra)));
    }

    private function callEndpoint(string $path, ?string $token)
    {
        return $this->getJson("/api/_test/{$path}", $token === null ? [] : ['Authorization' => 'Bearer '.$token]);
    }

    public function test_access_follows_the_explicit_role_matrix_with_no_hierarchy(): void
    {
        // endpoint => [role that may enter]
        $matrix = [
            'admin-only' => ['admin'],
            'lawyer-only' => ['lawyer'],
            'user-only' => ['user'],
            'lawyer-or-admin' => ['lawyer', 'admin'],
        ];

        foreach ($matrix as $path => $allowed) {
            foreach (['user', 'lawyer', 'admin'] as $role) {
                $response = $this->callEndpoint($path, $this->tokenWithRole($role));

                if (in_array($role, $allowed, true)) {
                    $response->assertOk()->assertJsonPath('data.reached', true);
                } else {
                    $response->assertStatus(403)->assertExactJson(self::FORBIDDEN);
                }
            }
        }
    }

    public function test_a_missing_role_counts_as_user(): void
    {
        $this->callEndpoint('user-only', $this->tokenWithRole(null))->assertOk();
        $this->callEndpoint('admin-only', $this->tokenWithRole(null))->assertStatus(403);
    }

    public function test_user_metadata_role_does_not_grant_access(): void
    {
        $token = $this->tokenWithRole(null, ['user_metadata' => ['full_name' => 'Sneaky', 'role' => 'admin']]);

        $this->callEndpoint('admin-only', $token)->assertStatus(403)->assertExactJson(self::FORBIDDEN);
        $this->callEndpoint('lawyer-or-admin', $token)->assertStatus(403);
    }

    public function test_an_invalid_app_metadata_role_does_not_grant_access(): void
    {
        foreach (['Admin', 'ADMIN', 'superadmin', 'administrator', ''] as $role) {
            $this->callEndpoint('admin-only', $this->tokenWithRole($role))->assertStatus(403);
        }
    }

    public function test_no_token_is_401_not_403(): void
    {
        foreach (['admin-only', 'lawyer-or-admin', 'user-only'] as $path) {
            $this->callEndpoint($path, null)
                ->assertStatus(401)
                ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.'])
                ->assertHeader('WWW-Authenticate', 'Bearer realm="LawAssist API"');
        }
    }

    public function test_an_invalid_token_is_401_not_403(): void
    {
        $this->callEndpoint('admin-only', $this->tokenWithRole('admin', ['exp' => time() - 3600]))
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="LawAssist API", error="invalid_token"');
    }

    public function test_the_role_middleware_authenticates_on_its_own(): void
    {
        $this->callEndpoint('role-without-auth-middleware', $this->tokenWithRole('admin'))->assertOk();
        $this->callEndpoint('role-without-auth-middleware', $this->tokenWithRole('user'))->assertStatus(403);
        $this->callEndpoint('role-without-auth-middleware', null)->assertStatus(401);
    }

    public function test_a_misconfigured_route_fails_closed_for_every_authenticated_caller(): void
    {
        config(['app.debug' => false]);

        foreach (['unknown-role', 'wrong-case-role', 'no-roles'] as $path) {
            foreach (['admin', 'lawyer', 'user'] as $role) {
                $this->callEndpoint($path, $this->tokenWithRole($role))
                    ->assertStatus(500)
                    ->assertExactJson(['success' => false, 'message' => 'Server error.']);
            }

            // Authentication runs first, so an anonymous caller is still told 401, never granted anything.
            $this->callEndpoint($path, null)->assertStatus(401);
        }
    }
}
