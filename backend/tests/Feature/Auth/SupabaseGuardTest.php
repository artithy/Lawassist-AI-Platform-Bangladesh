<?php

namespace Tests\Feature\Auth;

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
 * The "supabase" guard end to end: real signed tokens (locally generated keys, fake JWKS) sent to GET /api/me.
 * Verification itself is covered by SupabaseTokenVerifierTest; here we check what the guard does with the outcome.
 */
class SupabaseGuardTest extends TestCase
{
    use ConfiguresSupabase;
    use RefreshDatabase;

    private const PLAIN_CHALLENGE = 'Bearer realm="LawAssist API"';

    private const INVALID_TOKEN_CHALLENGE = 'Bearer realm="LawAssist API", error="invalid_token"';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();

        Route::middleware('auth:supabase')->get('api/_test/whoami', fn () => response()->json([
            'check' => Auth::guard('supabase')->check(),
            'guest' => Auth::guard('supabase')->guest(),
            'id' => Auth::guard('supabase')->id(),
            'current_guard' => Auth::getDefaultDriver(),
            'role' => Auth::guard('supabase')->user()->verifiedRole()?->value,
        ]));
    }

    /** @param  array<string, mixed>  $claims */
    private function tokenFor(array $claims = []): string
    {
        return $this->keys->sign(SupabaseTestKeys::claims($claims));
    }

    /** A brand-new Supabase user (fresh id and email) with the given extra claims. @param  array<string, mixed>  $claims */
    private function newUserToken(array $claims = []): string
    {
        $sub = (string) Str::uuid();

        return $this->tokenFor(array_replace(['sub' => $sub, 'email' => "u{$sub}@example.com"], $claims));
    }

    /** @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function callMe(?string $authorization, string $uri = '/api/me')
    {
        return $this->getJson($uri, $authorization === null ? [] : ['Authorization' => $authorization]);
    }

    // ---- success ---------------------------------------------------------------------------------------------

    public function test_a_valid_token_authenticates_and_provisions_the_user(): void
    {
        $response = $this->callMe('Bearer '.$this->tokenFor());

        $response->assertOk()->assertJsonPath('success', true);

        $user = User::forSupabaseUser(SupabaseTestKeys::SUBJECT)->firstOrFail();
        $this->assertSame('client@example.com', $user->email);
        $this->assertSame('Test Client', $user->name);
        $response->assertJsonPath('data.id', $user->id);
        $this->assertSame(1, User::count());
    }

    public function test_the_same_supabase_user_is_not_created_twice(): void
    {
        $token = $this->tokenFor();

        $this->callMe('Bearer '.$token)->assertOk();
        $this->callMe('Bearer '.$token)->assertOk();

        $this->assertSame(1, User::count());
    }

    public function test_an_existing_user_is_found_by_the_supabase_id(): void
    {
        $existing = User::factory()->create(['supabase_user_id' => SupabaseTestKeys::SUBJECT, 'email' => 'old@example.com']);

        $this->callMe('Bearer '.$this->tokenFor())
            ->assertOk()
            ->assertJsonPath('data.id', $existing->id)
            ->assertJsonPath('data.email', 'old@example.com');

        $this->assertSame(1, User::count());
    }

    public function test_the_scheme_may_be_written_in_any_letter_case(): void
    {
        $this->callMe('bearer '.$this->tokenFor())->assertOk();
    }

    public function test_each_request_is_authenticated_on_its_own_token(): void
    {
        $first = $this->callMe('Bearer '.$this->newUserToken())->assertOk()->json('data.id');
        $second = $this->callMe('Bearer '.$this->newUserToken())->assertOk()->json('data.id');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, User::count());
    }

    // ---- no credentials / unsupported or malformed header -> 401 ---------------------------------------------

    public function test_a_missing_authorization_header_is_401_with_a_plain_bearer_challenge(): void
    {
        $this->callMe(null)
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.'])
            ->assertHeader('WWW-Authenticate', self::PLAIN_CHALLENGE);

        $this->assertSame(0, User::count());
    }

    public function test_an_unsupported_scheme_is_401_with_a_plain_bearer_challenge(): void
    {
        $this->callMe('Basic '.base64_encode('user:pass'))
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.'])
            ->assertHeader('WWW-Authenticate', self::PLAIN_CHALLENGE);
    }

    public function test_a_token_in_the_query_string_is_ignored(): void
    {
        $token = $this->tokenFor();

        $this->callMe(null, '/api/me?access_token='.$token.'&token='.$token)
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', self::PLAIN_CHALLENGE);

        $this->assertSame(0, User::count());
    }

    public function test_a_malformed_bearer_header_is_401_invalid_token(): void
    {
        foreach (['Bearer', 'Bearer  '.$this->tokenFor(), 'Bearer not-a-jwt', 'Bearer a.b', 'Bearer '.$this->tokenFor().'x.y'] as $header) {
            $this->callMe($header)
                ->assertStatus(401)
                ->assertExactJson(['success' => false, 'message' => 'Invalid or expired access token.'])
                ->assertHeader('WWW-Authenticate', self::INVALID_TOKEN_CHALLENGE);
        }

        $this->assertSame(0, User::count());
    }

    public function test_responses_are_json_even_without_an_accept_header(): void
    {
        $this->get('/api/me')
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    // ---- tokens the verifier rejects -> 401, and nothing is provisioned --------------------------------------

    public function test_invalid_tokens_are_401_and_create_no_user(): void
    {
        $good = $this->tokenFor();
        [$header, $payload, $signature] = explode('.', $good);
        $tampered = $header.'.'.$payload.'.'.substr_replace($signature, $signature[10] === 'A' ? 'B' : 'A', 10, 1);

        $cases = [
            'expired' => $this->tokenFor(['exp' => time() - 3600]),
            'wrong audience' => $this->tokenFor(['aud' => 'someone-else']),
            'wrong issuer' => $this->tokenFor(['iss' => 'https://evil.example.com/auth/v1']),
            'anon role' => $this->tokenFor(['role' => 'anon']),
            'tampered signature' => $tampered,
            'unknown key id' => $this->keys->sign(SupabaseTestKeys::claims(), 'unknown-kid'),
            'alg none' => SupabaseTestKeys::raw(['alg' => 'none', 'typ' => 'JWT'], SupabaseTestKeys::claims(), ''),
        ];

        foreach ($cases as $name => $token) {
            $this->callMe('Bearer '.$token)
                ->assertStatus(401)
                ->assertExactJson(['success' => false, 'message' => 'Invalid or expired access token.'])
                ->assertHeader('WWW-Authenticate', self::INVALID_TOKEN_CHALLENGE);
        }

        $this->assertSame(0, User::count());
    }

    // ---- service problems and provisioning refusals ----------------------------------------------------------

    public function test_unreachable_keys_are_503_with_retry_after(): void
    {
        $this->jwksUnreachable = true;

        $this->callMe('Bearer '.$this->tokenFor())
            ->assertStatus(503)
            ->assertExactJson(['success' => false, 'message' => 'Authentication service unavailable.'])
            ->assertHeader('Retry-After', '5');

        $this->assertSame(0, User::count());
    }

    public function test_an_email_used_by_another_user_is_409_and_the_other_user_is_not_linked(): void
    {
        $other = User::factory()->create(['email' => 'client@example.com']);
        $originalId = $other->supabase_user_id;

        $this->callMe('Bearer '.$this->tokenFor())
            ->assertStatus(409)
            ->assertExactJson(['success' => false, 'message' => 'An account with this email already exists.']);

        $this->assertSame($originalId, $other->fresh()->supabase_user_id);
        $this->assertSame(1, User::count());
    }

    public function test_a_genuine_token_without_an_email_is_403(): void
    {
        $this->callMe('Bearer '.$this->tokenFor(['email' => null]))
            ->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'message' => 'Your account cannot be set up because it has no usable email address.',
            ]);

        $this->assertSame(0, User::count());
    }

    // ---- roles: only the verified app_metadata.role counts ---------------------------------------------------

    public function test_the_role_comes_from_app_metadata(): void
    {
        foreach (['user', 'lawyer', 'admin'] as $role) {
            $this->callMe('Bearer '.$this->newUserToken(['app_metadata' => ['role' => $role]]))
                ->assertOk()
                ->assertJsonPath('data.role', $role);
        }
    }

    public function test_a_missing_role_defaults_to_user(): void
    {
        // "Missing" means no usable role at all. An explicit but unsupported role is refused instead: see UnsupportedRoleTest.
        $roles = [
            'no role key' => ['provider' => 'google'],
            'empty app_metadata' => [],
            'null role' => ['role' => null],
        ];

        foreach ($roles as $name => $appMetadata) {
            $this->callMe('Bearer '.$this->newUserToken(['app_metadata' => $appMetadata]))
                ->assertOk()
                ->assertJsonPath('data.role', 'user');
        }
    }

    public function test_user_metadata_role_is_never_used(): void
    {
        $this->callMe('Bearer '.$this->newUserToken([
            'user_metadata' => ['full_name' => 'Sneaky', 'role' => 'admin'],
            'app_metadata' => ['provider' => 'google'],
        ]))->assertOk()->assertJsonPath('data.role', 'user');
    }

    public function test_the_top_level_role_claim_is_never_used_as_the_application_role(): void
    {
        // The genuine top-level "role" is the Postgres role "authenticated"; it must not become an application role.
        $this->callMe('Bearer '.$this->newUserToken(['app_metadata' => ['role' => 'lawyer']]))
            ->assertOk()
            ->assertJsonPath('data.role', 'lawyer');
    }

    public function test_the_role_is_never_stored_in_the_database(): void
    {
        $this->callMe('Bearer '.$this->newUserToken(['app_metadata' => ['role' => 'admin']]))->assertOk();

        foreach (DB::table('users')->get() as $row) {
            $this->assertObjectNotHasProperty('role', $row);
        }

        $this->assertArrayNotHasKey('role', User::first()->getAttributes());
    }

    public function test_a_role_change_applies_to_the_next_token_without_touching_the_user(): void
    {
        $sub = (string) Str::uuid();
        $claims = ['sub' => $sub, 'email' => 'same@example.com'];

        $this->callMe('Bearer '.$this->tokenFor($claims + ['app_metadata' => ['role' => 'user']]))
            ->assertOk()->assertJsonPath('data.role', 'user');
        $this->callMe('Bearer '.$this->tokenFor($claims + ['app_metadata' => ['role' => 'admin']]))
            ->assertOk()->assertJsonPath('data.role', 'admin');

        $this->assertSame(1, User::count());
    }

    // ---- compatibility with Laravel's auth system ------------------------------------------------------------

    public function test_the_guard_works_with_laravels_auth_api(): void
    {
        $response = $this->callMe('Bearer '.$this->tokenFor(['app_metadata' => ['role' => 'lawyer']]), '/api/_test/whoami');

        $response->assertOk()->assertJson([
            'check' => true,
            'guest' => false,
            'current_guard' => 'supabase', // the "auth:supabase" middleware makes it the current guard
            'role' => 'lawyer',
        ]);
        $this->assertSame(User::firstOrFail()->id, $response->json('id'));
    }

    public function test_the_default_guard_is_unchanged_and_sanctum_is_not_used(): void
    {
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertSame('supabase', config('auth.guards.supabase.driver'));
        $this->assertSame('session', config('auth.guards.web.driver'));
        $this->assertInstanceOf(\Illuminate\Auth\RequestGuard::class, Auth::guard('supabase'));

        // Sanctum is installed but unused: none of the application's routes goes through it.
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                $this->assertStringNotContainsString('sanctum', (string) $middleware, "{$route->uri()} uses Sanctum");
            }
        }
    }
}
