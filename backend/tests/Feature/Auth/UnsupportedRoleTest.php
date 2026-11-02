<?php

namespace Tests\Feature\Auth;

use App\Auth\Supabase\UnsupportedRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConfiguresSupabase;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

/**
 * An EXPLICIT app_metadata.role that is not exactly user/lawyer/admin (suspended, banned, "Admin", "", an array, ...) is
 * refused with a generic 403 before any account is looked up or created. A MISSING role still means "user".
 */
class UnsupportedRoleTest extends TestCase
{
    use ConfiguresSupabase;
    use RefreshDatabase;

    private const MESSAGE = 'This account is not allowed to use this application.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupabase();
    }

    /** @return array<string, array{0: mixed}> */
    public static function unsupportedRoles(): array
    {
        return [
            'suspended' => ['suspended'],
            'banned' => ['banned'],
            'disabled' => ['disabled'],
            'blocked' => ['blocked'],
            'unknown word' => ['superadmin'],
            'capitalised' => ['Admin'],
            'upper case' => ['ADMIN'],
            'leading space' => [' admin'],
            'trailing space' => ['lawyer '],
            'trailing newline' => ["user\n"],
            'empty string' => [''],
            'lookalike' => ['adm1n'],
            'array' => [['admin']],
            'integer' => [1],
            'zero' => [0],
            'true' => [true],
            'false' => [false],
            'object' => [['name' => 'admin']],
        ];
    }

    private function bearer(mixed $role, ?string $sub = null, array $extra = []): array
    {
        $sub ??= (string) Str::uuid();

        return ['Authorization' => 'Bearer '.$this->keys->sign(SupabaseTestKeys::claims(array_replace([
            'sub' => $sub,
            'email' => "u{$sub}@example.com",
            'app_metadata' => ['role' => $role],
        ], $extra)))];
    }

    #[DataProvider('unsupportedRoles')]
    public function test_an_explicit_unsupported_role_is_refused_with_a_generic_403(mixed $role): void
    {
        $this->getJson('/api/me', $this->bearer($role))
            ->assertStatus(403)
            ->assertExactJson(['success' => false, 'message' => self::MESSAGE])
            ->assertHeaderMissing('WWW-Authenticate');
    }

    #[DataProvider('unsupportedRoles')]
    public function test_no_account_is_created_for_an_unsupported_role(mixed $role): void
    {
        $this->getJson('/api/me', $this->bearer($role))->assertStatus(403);

        $this->assertSame(0, User::count());
    }

    public function test_an_existing_account_is_refused_too_and_left_untouched(): void
    {
        $sub = (string) Str::uuid();

        $this->getJson('/api/me', $this->bearer('user', $sub))->assertOk(); // provisioned while the role was fine
        $before = User::firstOrFail()->only(['id', 'name', 'email', 'supabase_user_id', 'updated_at']);

        $this->getJson('/api/me', $this->bearer('banned', $sub))->assertStatus(403);
        $this->getJson('/api/me', $this->bearer('user', $sub))->assertOk(); // back to a supported role: access again

        $this->assertSame(1, User::count());
        $this->assertEquals($before, User::firstOrFail()->only(['id', 'name', 'email', 'supabase_user_id', 'updated_at']));
    }

    public function test_the_refusal_comes_before_the_email_checks(): void
    {
        // Neither the email-conflict check (409) nor the missing-email check (403 + other message) runs for a refused role.
        User::factory()->create(['email' => 'client@example.com']);

        $this->getJson('/api/me', $this->bearer('suspended', null, ['email' => 'client@example.com']))
            ->assertStatus(403)->assertExactJson(['success' => false, 'message' => self::MESSAGE]);
        $this->getJson('/api/me', $this->bearer('suspended', null, ['email' => null]))
            ->assertStatus(403)->assertExactJson(['success' => false, 'message' => self::MESSAGE]);
    }

    public function test_role_protected_routes_refuse_it_before_the_role_check(): void
    {
        Route::middleware(['auth:supabase', 'role:user,lawyer,admin'])->get('api/_test/any-role', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/_test/any-role', $this->bearer('suspended'))->assertStatus(403)->assertExactJson(['success' => false, 'message' => self::MESSAGE]);
        $this->getJson('/api/_test/any-role', $this->bearer('admin'))->assertOk();
    }

    // ---- what is NOT explicit stays "user" -------------------------------------------------------------------

    public function test_a_missing_role_is_still_user(): void
    {
        $cases = [
            'no role key' => ['provider' => 'google'],
            'empty app_metadata' => [],
            'null role' => ['role' => null],
        ];

        foreach ($cases as $name => $appMetadata) {
            $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->keys->sign(SupabaseTestKeys::claims([
                'sub' => $sub = (string) Str::uuid(),
                'email' => "u{$sub}@example.com",
                'app_metadata' => $appMetadata,
            ]))])->assertOk()->assertJsonPath('data.role', 'user');
        }
    }

    public function test_no_app_metadata_at_all_is_still_user(): void
    {
        $claims = SupabaseTestKeys::claims(['sub' => $sub = (string) Str::uuid(), 'email' => "u{$sub}@example.com"]);
        unset($claims['app_metadata']);

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->keys->sign($claims)])
            ->assertOk()->assertJsonPath('data.role', 'user');
    }

    public function test_the_three_supported_roles_still_work(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->getJson('/api/me', $this->bearer($role->value))->assertOk()->assertJsonPath('data.role', $role->value);
        }
    }

    public function test_user_metadata_role_is_never_looked_at_for_this_either(): void
    {
        // A self-editable user_metadata.role of "banned" or "admin" neither refuses nor grants anything.
        foreach (['banned', 'admin'] as $value) {
            $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->keys->sign(SupabaseTestKeys::claims([
                'sub' => $sub = (string) Str::uuid(),
                'email' => "u{$sub}@example.com",
                'app_metadata' => ['provider' => 'google'],
                'user_metadata' => ['full_name' => 'Someone', 'role' => $value],
            ]))])->assertOk()->assertJsonPath('data.role', 'user');
        }
    }

    // ---- the model of the decision: enum + verified token ---------------------------------------------------

    #[DataProvider('unsupportedRoles')]
    public function test_the_enum_and_the_verified_token_flag_unsupported_roles(mixed $role): void
    {
        $this->assertTrue(UserRole::hasUnsupportedRole(['role' => $role]));
        $this->assertSame(UserRole::User, UserRole::fromAppMetadata(['role' => $role])); // the approved default is unchanged
    }

    public function test_the_enum_does_not_flag_what_is_missing_or_supported(): void
    {
        foreach ([null, 'admin', 'user', [], ['provider' => 'x'], ['role' => null], ['role' => 'user'], ['role' => 'lawyer'], ['role' => 'admin']] as $appMetadata) {
            $this->assertFalse(UserRole::hasUnsupportedRole($appMetadata), json_encode($appMetadata));
        }
    }

    // ---- logging: one structured line, reason code only ------------------------------------------------------

    public function test_the_refusal_is_logged_once_with_only_the_reason_code(): void
    {
        $roleValue = 'suspended-XYZ-secret-marker';

        // withArgs() must come before once(): Mockery checks the count against the filters declared so far.
        Log::shouldReceive('warning')
            ->withArgs(function (string $message, array $context = []) use ($roleValue): bool {
                return $message === 'Supabase access refused: unsupported role.'
                    && $context === ['reason' => 'unsupported_role']
                    && ! str_contains(json_encode($context), $roleValue);
            })
            ->once();
        Log::shouldReceive('error')->never();

        $this->getJson('/api/me', $this->bearer($roleValue))->assertStatus(403);
    }

    public function test_the_log_never_contains_the_role_the_email_or_the_token(): void
    {
        $lines = [];
        Log::shouldReceive('warning')->andReturnUsing(function (string $message, array $context = []) use (&$lines) {
            $lines[] = $message.' '.json_encode($context);
        });

        $headers = $this->bearer('banned-role-marker', null, ['email' => 'marker.person@example.com']);
        $this->getJson('/api/me', $headers)->assertStatus(403);

        $this->assertCount(1, $lines);
        foreach (['banned-role-marker', 'marker.person', substr($headers['Authorization'], 20, 30)] as $secret) {
            $this->assertStringNotContainsString($secret, $lines[0]);
        }
    }

    public function test_the_exception_carries_only_a_reason_code(): void
    {
        $e = new UnsupportedRole;

        $this->assertSame('unsupported_role', $e->reason);
        $this->assertSame('Unsupported role.', $e->getMessage());
    }

    public function test_a_supported_or_missing_role_logs_nothing(): void
    {
        Log::shouldReceive('warning')->never();
        Log::shouldReceive('error')->never();

        $this->getJson('/api/me', $this->bearer('admin'))->assertOk();
        $this->getJson('/api/me', $this->bearer(null))->assertOk();
    }
}
