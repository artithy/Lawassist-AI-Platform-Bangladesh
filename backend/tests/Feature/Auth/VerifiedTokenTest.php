<?php

namespace Tests\Feature\Auth;

use App\Auth\Supabase\VerifiedToken;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SupabaseTestKeys;
use Tests\TestCase;

class VerifiedTokenTest extends TestCase
{
    private function makeVerifiedToken(array $overrides = []): VerifiedToken
    {
        return VerifiedToken::fromClaims(SupabaseTestKeys::claims($overrides), 'ES256', 'k1');
    }

    public static function roleCases(): array
    {
        return [
            'admin' => ['admin', UserRole::Admin],
            'lawyer' => ['lawyer', UserRole::Lawyer],
            'user' => ['user', UserRole::User],
            'unknown' => ['superuser', UserRole::User],
            'wrong case' => ['Admin', UserRole::User],
            'padded' => [' admin', UserRole::User],
            'empty' => ['', UserRole::User],
        ];
    }

    #[DataProvider('roleCases')]
    public function test_role_comes_from_app_metadata(string $value, UserRole $expected): void
    {
        $this->assertSame($expected, $this->makeVerifiedToken(['app_metadata' => ['role' => $value]])->role);
    }

    public function test_role_defaults_to_user_when_missing_or_not_a_string(): void
    {
        $this->assertSame(UserRole::User, $this->makeVerifiedToken(['app_metadata' => ['provider' => 'google']])->role);
        $this->assertSame(UserRole::User, $this->makeVerifiedToken(['app_metadata' => []])->role);
        $this->assertSame(UserRole::User, $this->makeVerifiedToken(['app_metadata' => 'admin'])->role);
        $this->assertSame(UserRole::User, $this->makeVerifiedToken(['app_metadata' => ['role' => ['admin']]])->role);
        $this->assertSame(UserRole::User, $this->makeVerifiedToken(['app_metadata' => ['role' => true]])->role);

        $claims = SupabaseTestKeys::claims();
        unset($claims['app_metadata']);
        $this->assertSame(UserRole::User, VerifiedToken::fromClaims($claims, 'ES256', null)->role);
    }

    public function test_user_metadata_can_never_grant_a_role(): void
    {
        $token = $this->makeVerifiedToken([
            'user_metadata' => ['role' => 'admin', 'app_metadata' => ['role' => 'admin'], 'is_admin' => true],
        ]);

        $this->assertSame(UserRole::User, $token->role);
    }

    public function test_the_top_level_role_claim_is_not_the_application_role(): void
    {
        // "role" is the Postgres role ("authenticated"); even if it said "admin" it must not become an app role.
        $this->assertSame(UserRole::User, $this->makeVerifiedToken(['role' => 'admin'])->role);
        $this->assertSame(UserRole::Admin, $this->makeVerifiedToken(['role' => 'admin', 'app_metadata' => ['role' => 'admin']])->role);
    }

    public function test_email_is_normalised_and_invalid_values_are_dropped(): void
    {
        $this->assertSame('mixed.case@example.com', $this->makeVerifiedToken(['email' => '  Mixed.Case@Example.COM '])->email);
        $this->assertNull($this->makeVerifiedToken(['email' => 'not-an-email'])->email);
        $this->assertNull($this->makeVerifiedToken(['email' => ['a@example.com']])->email);

        $claims = SupabaseTestKeys::claims();
        unset($claims['email']);
        $this->assertNull(VerifiedToken::fromClaims($claims, 'ES256', null)->email);
    }

    public function test_display_name_is_cleaned_and_limited(): void
    {
        $this->assertSame('Ada Lovelace', $this->makeVerifiedToken(['user_metadata' => ['full_name' => "  Ada\n\tLovelace\x00 "]])->displayName);
        $this->assertSame('Fallback Name', $this->makeVerifiedToken(['user_metadata' => ['name' => 'Fallback Name']])->displayName);
        $this->assertSame(255, mb_strlen($this->makeVerifiedToken(['user_metadata' => ['full_name' => str_repeat('é', 400)]])->displayName));
        $this->assertNull($this->makeVerifiedToken(['user_metadata' => ['full_name' => '   ']])->displayName);
        $this->assertNull($this->makeVerifiedToken(['user_metadata' => ['full_name' => ['x']]])->displayName);
        $this->assertNull($this->makeVerifiedToken(['user_metadata' => 'oops'])->displayName);
    }

    public function test_exposes_expiry_algorithm_and_key_id(): void
    {
        $token = $this->makeVerifiedToken(['exp' => 2000000000]);

        $this->assertSame(2000000000, $token->expiresAt);
        $this->assertSame('ES256', $token->algorithm);
        $this->assertSame('k1', $token->keyId);
    }
}
