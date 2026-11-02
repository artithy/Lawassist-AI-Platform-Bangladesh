<?php

namespace Tests\Feature\Auth;

use App\Auth\Supabase\AccountNotProvisionable;
use App\Auth\Supabase\EmailConflict;
use App\Auth\Supabase\SupabaseUserProvisioner;
use App\Auth\Supabase\VerifiedToken;
use App\Enums\UserRole;
use App\Models\Lawyer;
use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SupabaseUserProvisioner: find by Supabase id, create on first login, never link by email, handle concurrent first
 * logins, never persist the role. Tokens are built directly as VerifiedToken (the verifier has its own tests).
 */
class SupabaseUserProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private function provisioner(): SupabaseUserProvisioner
    {
        return app(SupabaseUserProvisioner::class);
    }

    /** @param array<string, mixed> $overrides */
    private function makeToken(array $overrides = []): VerifiedToken
    {
        $values = array_merge([
            'subject' => (string) Str::uuid(),
            'role' => UserRole::User,
            'email' => 'new.user@example.com',
            'displayName' => 'New User',
            'expiresAt' => time() + 3600,
            'algorithm' => 'ES256',
            'keyId' => 'test-key-1',
        ], $overrides);

        return new VerifiedToken(
            $values['subject'],
            $values['role'],
            $values['email'],
            $values['displayName'],
            $values['expiresAt'],
            $values['algorithm'],
            $values['keyId'],
        );
    }

    // ---- first login ---------------------------------------------------------------------------------------

    public function test_the_first_login_creates_a_user_mapped_to_the_supabase_id(): void
    {
        $token = $this->makeToken(['email' => 'new.user@example.com', 'displayName' => 'New User']);

        $user = $this->provisioner()->provision($token);

        $this->assertTrue($user->exists);
        $this->assertSame($token->subject, $user->supabase_user_id);
        $this->assertSame('new.user@example.com', $user->email);
        $this->assertSame('New User', $user->name);
        $this->assertSame(1, User::count());
        $this->assertTrue(User::forSupabaseUser($token->subject)->firstOrFail()->is($user));
    }

    public function test_email_verified_at_stays_null_for_a_new_user(): void
    {
        $user = $this->provisioner()->provision($this->makeToken());

        $this->assertNull($user->email_verified_at);
        $this->assertNull(User::find($user->id)->email_verified_at);
    }

    public function test_a_new_user_gets_an_unguessable_hashed_password(): void
    {
        $first = $this->provisioner()->provision($this->makeToken(['email' => 'a@example.com']));
        $second = $this->provisioner()->provision($this->makeToken(['email' => 'b@example.com']));

        foreach ([$first, $second] as $user) {
            $hash = User::find($user->id)->password;

            $this->assertNotSame('unknown', password_get_info($hash)['algoName']);
            $this->assertFalse(Hash::check('password', $hash));
            $this->assertFalse(Hash::check('', $hash));
        }

        $this->assertNotSame($first->password, $second->password); // random per user
    }

    public function test_the_name_falls_back_to_the_part_of_the_email_before_the_at_sign(): void
    {
        $user = $this->provisioner()->provision($this->makeToken(['email' => 'rahim.uddin@example.com', 'displayName' => null]));

        $this->assertSame('rahim.uddin', $user->name);
    }

    public function test_the_name_is_limited_to_255_characters(): void
    {
        $user = $this->provisioner()->provision($this->makeToken(['displayName' => str_repeat('a', 400)]));

        $this->assertSame(255, mb_strlen($user->name));
    }

    public function test_supabase_user_id_is_not_mass_assignable(): void
    {
        $this->assertFalse((new User)->isFillable('supabase_user_id'));

        $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'p', 'supabase_user_id' => (string) Str::uuid()]);

        $this->assertNull($user->supabase_user_id);
    }

    // ---- existing users ------------------------------------------------------------------------------------

    public function test_an_existing_mapped_user_is_returned_and_nothing_is_created(): void
    {
        $existing = User::factory()->create(['supabase_user_id' => $sub = (string) Str::uuid()]);

        $user = $this->provisioner()->provision($this->makeToken(['subject' => $sub]));

        $this->assertTrue($user->is($existing));
        $this->assertSame(1, User::count());
    }

    public function test_provisioning_twice_with_the_same_token_creates_one_user(): void
    {
        $token = $this->makeToken();

        $first = $this->provisioner()->provision($token);
        $second = $this->provisioner()->provision($token);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, User::count());
    }

    public function test_an_existing_user_is_not_changed_by_a_token_with_different_data(): void
    {
        $existing = User::factory()->create([
            'supabase_user_id' => $sub = (string) Str::uuid(),
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->provisioner()->provision($this->makeToken([
            'subject' => $sub, 'email' => 'changed@example.com', 'displayName' => 'Changed Name',
        ]));

        $fresh = $existing->fresh();
        $this->assertSame('Original Name', $fresh->name);
        $this->assertSame('original@example.com', $fresh->email);
    }

    public function test_an_existing_mapped_user_is_returned_even_if_the_token_has_no_email(): void
    {
        $existing = User::factory()->create(['supabase_user_id' => $sub = (string) Str::uuid()]);

        $user = $this->provisioner()->provision($this->makeToken(['subject' => $sub, 'email' => null]));

        $this->assertTrue($user->is($existing));
    }

    public function test_an_existing_mapped_user_is_returned_even_if_the_token_email_belongs_to_someone_else(): void
    {
        $existing = User::factory()->create(['supabase_user_id' => $sub = (string) Str::uuid()]);
        User::factory()->create(['email' => 'someone.else@example.com']);

        $user = $this->provisioner()->provision($this->makeToken(['subject' => $sub, 'email' => 'someone.else@example.com']));

        $this->assertTrue($user->is($existing));
        $this->assertSame(2, User::count());
    }

    // ---- email conflicts (409): never link by email --------------------------------------------------------

    public function test_an_email_used_by_an_unlinked_user_is_a_conflict_and_the_user_is_not_linked(): void
    {
        $other = User::factory()->unlinked()->create(['email' => 'taken@example.com']);
        $token = $this->makeToken(['email' => 'taken@example.com']);

        try {
            $this->provisioner()->provision($token);
            $this->fail('Expected EmailConflict.');
        } catch (EmailConflict $e) {
            $this->assertSame('email_conflict', $e->reason);
        }

        $this->assertNull($other->fresh()->supabase_user_id); // NOT auto-linked
        $this->assertSame(1, User::count());
        $this->assertNull(User::forSupabaseUser($token->subject)->first());
    }

    public function test_an_email_used_by_a_user_linked_to_another_supabase_account_is_a_conflict(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $originalId = $other->supabase_user_id;

        $this->expectException(EmailConflict::class);

        try {
            $this->provisioner()->provision($this->makeToken(['email' => 'taken@example.com']));
        } finally {
            $this->assertSame($originalId, $other->fresh()->supabase_user_id);
            $this->assertSame(1, User::count());
        }
    }

    public function test_the_email_conflict_check_ignores_letter_case(): void
    {
        User::factory()->unlinked()->create(['email' => 'Mixed.Case@Example.com']);

        $this->expectException(EmailConflict::class);

        $this->provisioner()->provision($this->makeToken(['email' => 'mixed.case@example.com']));
    }

    // ---- no usable email (403) -----------------------------------------------------------------------------

    public function test_a_token_without_an_email_cannot_create_a_user(): void
    {
        try {
            $this->provisioner()->provision($this->makeToken(['email' => null]));
            $this->fail('Expected AccountNotProvisionable.');
        } catch (AccountNotProvisionable $e) {
            $this->assertSame('no_usable_email', $e->reason);
        }

        $this->assertSame(0, User::count());
    }

    // ---- roles and lawyer profiles are never persisted or linked ------------------------------------------

    public function test_the_role_is_never_persisted(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'role'));

        foreach (UserRole::cases() as $i => $role) {
            $user = $this->provisioner()->provision($this->makeToken([
                'role' => $role,
                'email' => "person{$i}@example.com",
            ]));

            $this->assertArrayNotHasKey('role', $user->getAttributes());
            $this->assertArrayNotHasKey('role', User::find($user->id)->getAttributes());
            $this->assertArrayNotHasKey('role', User::find($user->id)->toArray());
        }

        foreach (DB::table('users')->get() as $row) {
            $this->assertObjectNotHasProperty('role', $row);
        }
    }

    public function test_a_lawyer_profile_with_the_same_email_is_never_linked(): void
    {
        $lawyer = Lawyer::factory()->create(['email' => 'new.user@example.com']);

        $user = $this->provisioner()->provision($this->makeToken([
            'role' => UserRole::Lawyer,
            'email' => 'new.user@example.com',
        ]));

        $this->assertNull($lawyer->fresh()->user_id);
        $this->assertNull($user->lawyerProfile);
    }

    // ---- concurrent first logins ---------------------------------------------------------------------------

    /**
     * Simulates a second request that inserts its row after our "is there a user?" checks but before our own insert.
     * (PHPUnit cannot run requests in parallel, so the "other request" runs inside the model's creating event.)
     *
     * @param  array<string, mixed>  $competitor  columns of the row the other request creates
     */
    private function competitorInsertsBeforeOurInsert(array $competitor): void
    {
        $done = false;

        User::creating(function () use ($competitor, &$done) {
            if ($done) {
                return;
            }

            $done = true;

            DB::table('users')->insert(array_merge([
                'name' => 'Competitor',
                'password' => Hash::make(Str::random(40)),
                'created_at' => now(),
                'updated_at' => now(),
            ], $competitor));
        });
    }

    public function test_a_concurrent_first_login_for_the_same_supabase_user_returns_the_winner(): void
    {
        $sub = (string) Str::uuid();
        $this->competitorInsertsBeforeOurInsert(['supabase_user_id' => $sub, 'email' => 'winner@example.com']);

        $user = $this->provisioner()->provision($this->makeToken(['subject' => $sub, 'email' => 'loser@example.com']));

        $this->assertSame('winner@example.com', $user->email); // the other request's row, not a duplicate
        $this->assertSame($sub, $user->supabase_user_id);
        $this->assertSame(1, User::count());
    }

    public function test_a_concurrent_signup_with_the_same_email_for_a_different_user_is_a_conflict(): void
    {
        $this->competitorInsertsBeforeOurInsert([
            'supabase_user_id' => (string) Str::uuid(),
            'email' => 'taken@example.com',
        ]);
        $token = $this->makeToken(['email' => 'taken@example.com']);

        $this->expectException(EmailConflict::class);

        try {
            $this->provisioner()->provision($token);
        } finally {
            $this->assertSame(1, User::count());
            $this->assertNull(User::forSupabaseUser($token->subject)->first());
        }
    }

    // ---- nothing unrelated is swallowed --------------------------------------------------------------------

    public function test_an_unrelated_database_error_is_not_swallowed(): void
    {
        User::creating(function () {
            throw new QueryException('sqlite', 'insert into users ...', [], new Exception('simulated database failure'));
        });

        $this->expectException(QueryException::class);

        $this->provisioner()->provision($this->makeToken());
    }

    public function test_a_unique_violation_that_matches_neither_case_is_rethrown(): void
    {
        // Neither our Supabase id nor our email exists afterwards, so this is something we do not understand.
        User::creating(function () {
            throw new UniqueConstraintViolationException('sqlite', 'insert into users ...', [], new Exception('simulated'));
        });

        $this->expectException(UniqueConstraintViolationException::class);

        $this->provisioner()->provision($this->makeToken());
    }
}
