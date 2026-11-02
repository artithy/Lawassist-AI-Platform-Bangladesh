<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\ConfiguresSupabase;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

/** GET /api/me: the standard envelope, exactly five fields, nothing sensitive. */
class MeEndpointTest extends TestCase
{
    use ConfiguresSupabase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();
    }

    /** @param  array<string, mixed>  $claims */
    private function me(array $claims = [], ?string &$token = null)
    {
        $token = $this->keys->sign(SupabaseTestKeys::claims($claims));

        return $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token]);
    }

    public function test_it_returns_the_standard_envelope_with_exactly_the_five_fields(): void
    {
        $response = $this->me(['app_metadata' => ['role' => 'lawyer']]);
        $user = User::firstOrFail();

        $response->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Current user.',
            'data' => [
                'id' => $user->id,
                'supabase_user_id' => SupabaseTestKeys::SUBJECT,
                'name' => 'Test Client',
                'email' => 'client@example.com',
                'role' => 'lawyer',
            ],
        ]);

        $this->assertSame(['id', 'supabase_user_id', 'name', 'email', 'role'], array_keys($response->json('data')));
    }

    public function test_the_role_defaults_to_user(): void
    {
        $this->me()->assertOk()->assertJsonPath('data.role', 'user');
    }

    public function test_it_never_exposes_the_token_credentials_or_other_claims(): void
    {
        $response = $this->me([
            'app_metadata' => ['role' => 'admin', 'internal_flag' => 'INTERNAL_FLAG_VALUE', 'provider' => 'google'],
            'user_metadata' => ['full_name' => 'Test Client', 'secret_note' => 'SECRET_NOTE_VALUE', 'role' => 'admin'],
            'phone' => '+8801000000000',
            'session_id' => 'SESSION_ID_VALUE',
        ], $token);

        $body = $response->getContent();

        foreach ([
            $token, 'password', 'remember_token', 'email_verified_at', 'created_at', 'updated_at',
            'INTERNAL_FLAG_VALUE', 'SECRET_NOTE_VALUE', 'SESSION_ID_VALUE', '+8801000000000',
            'app_metadata', 'user_metadata', 'iss', 'aud', 'exp', 'kid', 'ES256',
        ] as $forbidden) {
            $this->assertStringNotContainsString((string) $forbidden, $body, "/api/me leaked {$forbidden}");
        }

        $this->assertSame('admin', $response->json('data.role')); // the verified app_metadata role, and nothing else
    }

    public function test_user_metadata_role_is_not_reported_as_the_role(): void
    {
        $this->me([
            'app_metadata' => ['provider' => 'google'],
            'user_metadata' => ['full_name' => 'Sneaky', 'role' => 'admin'],
        ])->assertOk()->assertJsonPath('data.role', 'user');
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_only_get_is_allowed(): void
    {
        $token = $this->keys->sign(SupabaseTestKeys::claims());

        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}('/api/me', [], ['Authorization' => 'Bearer '.$token])->assertStatus(405);
        }
    }
}
