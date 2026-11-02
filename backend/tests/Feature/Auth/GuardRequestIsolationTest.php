<?php

namespace Tests\Feature\Auth;

use App\Auth\Supabase\SupabaseGuard;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\ConfiguresSupabase;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

/**
 * Regression tests for a long-lived worker (Octane, Swoole, RoadRunner, FrankenPHP): ONE booted application serves many
 * requests, so the guard object is shared. Nothing in this file calls Auth::forgetGuards(), on purpose: the guard itself
 * must not carry a user from one request to the next.
 */
class GuardRequestIsolationTest extends TestCase
{
    use ConfiguresSupabase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();
    }

    private function bearer(string $role, ?string $sub = null, array $extra = []): array
    {
        $sub ??= (string) Str::uuid();

        return ['Authorization' => 'Bearer '.$this->keys->sign(SupabaseTestKeys::claims(array_replace([
            'sub' => $sub,
            'email' => "u{$sub}@example.com",
            'app_metadata' => ['role' => $role],
        ], $extra)))];
    }

    public function test_an_admin_request_then_a_user_request_then_an_anonymous_request_are_isolated(): void
    {
        $guardBefore = Auth::guard('supabase');
        $this->assertInstanceOf(SupabaseGuard::class, $guardBefore);

        // 1. admin
        $admin = $this->getJson('/api/me', $this->bearer('admin'));
        $admin->assertOk()->assertJsonPath('data.role', 'admin');

        // 2. a different, ordinary user: must be THEM, not the admin left in the guard
        $user = $this->getJson('/api/me', $this->bearer('user'));
        $user->assertOk()->assertJsonPath('data.role', 'user');
        $this->assertNotSame($admin->json('data.id'), $user->json('data.id'));
        $this->assertNotSame($admin->json('data.email'), $user->json('data.email'));

        // 3. anonymous: must be rejected, not served as the previous user
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);

        // The very same guard object served all three requests (this is what a worker does).
        $this->assertSame($guardBefore, Auth::guard('supabase'));
        $this->assertSame(2, User::count());
    }

    public function test_the_role_of_one_request_never_reaches_the_next_on_role_protected_routes(): void
    {
        Route::middleware(['auth:supabase', 'role:admin'])->get('api/_test/admin-area', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/_test/admin-area', $this->bearer('admin'))->assertOk();
        $this->getJson('/api/_test/admin-area', $this->bearer('user'))->assertStatus(403);
        $this->getJson('/api/_test/admin-area', $this->bearer('lawyer'))->assertStatus(403);
        $this->getJson('/api/_test/admin-area')->assertStatus(401);
        $this->getJson('/api/_test/admin-area', $this->bearer('admin'))->assertOk();
        $this->getJson('/api/_test/admin-area')->assertStatus(401);
    }

    public function test_an_invalid_token_after_a_valid_one_is_rejected(): void
    {
        $this->getJson('/api/me', $this->bearer('admin'))->assertOk();

        $this->getJson('/api/me', $this->bearer('admin', null, ['exp' => time() - 3600]))
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="LawAssist API", error="invalid_token"');

        $this->getJson('/api/me', ['Authorization' => 'Bearer not-a-jwt'])->assertStatus(401);
        $this->getJson('/api/me', ['Authorization' => 'Basic '.base64_encode('a:b')])->assertStatus(401);
    }

    public function test_the_same_user_can_call_again_and_is_not_duplicated(): void
    {
        $sub = (string) Str::uuid();

        $first = $this->getJson('/api/me', $this->bearer('lawyer', $sub))->assertOk()->json('data.id');
        $second = $this->getJson('/api/me', $this->bearer('lawyer', $sub))->assertOk()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, User::count());
    }

    public function test_a_failed_request_does_not_leave_anything_behind_for_the_next_one(): void
    {
        $this->getJson('/api/me', $this->bearer('admin', null, ['email' => null]))->assertStatus(403); // not provisionable
        $this->getJson('/api/me')->assertStatus(401);
        $this->getJson('/api/me', $this->bearer('user'))->assertOk()->assertJsonPath('data.role', 'user');
    }

    public function test_within_one_request_the_user_is_resolved_only_once(): void
    {
        Route::middleware(['auth:supabase', 'role:admin'])->get('api/_test/many-lookups', function () {
            // Many parts of an application ask for the user: middleware, policies, controllers, views.
            Auth::user();
            Auth::guard('supabase')->user();
            Auth::id();
            request()->user('supabase');

            return response()->json(['id' => Auth::guard('supabase')->id()]);
        });

        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'from "users"') && str_contains($query->sql, 'supabase_user_id')) {
                $queries++;
            }
        });

        $this->getJson('/api/_test/many-lookups', $this->bearer('admin'))->assertOk();

        $this->assertSame(1, $queries, 'the guard must look the user up once per request');
    }

    public function test_a_user_attached_on_purpose_with_acting_as_still_works(): void
    {
        // Feature tests (and later code) attach users with actingAs(); the guard must not throw such a user away.
        $user = User::factory()->create();
        $user->forceFill(['supabase_user_id' => (string) Str::uuid()])->save();

        $this->actingAs($user->withVerifiedRole(UserRole::Admin), 'supabase')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_forgetting_the_user_makes_the_guard_resolve_again(): void
    {
        $this->getJson('/api/me', $this->bearer('admin'))->assertOk();

        $guard = Auth::guard('supabase');
        $first = $guard->user();
        $this->assertSame($first, $guard->user()); // remembered within the request

        $guard->forgetUser();
        $second = $guard->user(); // the same request still carries the token, so it is resolved afresh

        $this->assertNotSame($first, $second);
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(UserRole::Admin, $second->verifiedRole());
    }
}
