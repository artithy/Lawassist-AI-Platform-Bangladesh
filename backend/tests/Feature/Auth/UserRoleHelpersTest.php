<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The verified role lives only on the User instance of the current request. It is never an attribute, never saved,
 * and there is no role hierarchy.
 */
class UserRoleHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_has_no_verified_role_by_default_and_every_check_fails_closed(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->verifiedRole());
        $this->assertFalse($user->hasRole(UserRole::User));
        $this->assertFalse($user->hasRole(UserRole::User, UserRole::Lawyer, UserRole::Admin));
    }

    public function test_with_verified_role_attaches_the_role_and_returns_the_same_instance(): void
    {
        $user = User::factory()->create();

        $this->assertSame($user, $user->withVerifiedRole(UserRole::Lawyer));
        $this->assertSame(UserRole::Lawyer, $user->verifiedRole());
    }

    public function test_has_role_is_an_exact_match_with_no_hierarchy(): void
    {
        $matrix = [
            [UserRole::User, [UserRole::User], true],
            [UserRole::User, [UserRole::Lawyer], false],
            [UserRole::User, [UserRole::Admin], false],
            [UserRole::Lawyer, [UserRole::Lawyer], true],
            [UserRole::Lawyer, [UserRole::User], false],
            [UserRole::Lawyer, [UserRole::Admin], false],
            [UserRole::Admin, [UserRole::Admin], true],
            [UserRole::Admin, [UserRole::User], false],     // admin does NOT imply user
            [UserRole::Admin, [UserRole::Lawyer], false],   // admin does NOT imply lawyer
            [UserRole::Lawyer, [UserRole::User, UserRole::Lawyer], true],
            [UserRole::Lawyer, [UserRole::User, UserRole::Admin], false],
        ];

        foreach ($matrix as [$verified, $required, $expected]) {
            $user = User::factory()->make()->withVerifiedRole($verified);

            $this->assertSame(
                $expected,
                $user->hasRole(...$required),
                "{$verified->value} vs ".implode(',', array_map(fn (UserRole $r) => $r->value, $required)),
            );
        }
    }

    public function test_has_role_with_no_roles_given_is_false(): void
    {
        $user = User::factory()->make()->withVerifiedRole(UserRole::Admin);

        $this->assertFalse($user->hasRole());
    }

    public function test_the_role_is_not_an_attribute_and_never_leaves_the_model(): void
    {
        $user = User::factory()->create()->withVerifiedRole(UserRole::Admin);

        $this->assertArrayNotHasKey('role', $user->getAttributes());
        $this->assertArrayNotHasKey('role', $user->toArray());
        $this->assertStringNotContainsString('admin', strtolower($user->toJson()));
        $this->assertFalse($user->isDirty());
    }

    public function test_saving_never_persists_the_role(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'role'));

        $user = User::factory()->create()->withVerifiedRole(UserRole::Admin);
        $user->name = 'Changed';
        $user->save();

        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertObjectNotHasProperty('role', $row);
        $this->assertSame('Changed', $row->name);
    }

    public function test_a_reloaded_or_copied_user_has_no_verified_role(): void
    {
        $user = User::factory()->create()->withVerifiedRole(UserRole::Admin);

        $this->assertNull($user->fresh()->verifiedRole());
        $this->assertNull($user->replicate()->verifiedRole());
        $this->assertNull(User::find($user->id)->verifiedRole());
    }

    // ---- serialization: the role must not travel with a stored copy of the user ------------------------------

    public function test_the_role_does_not_survive_serialize_and_unserialize(): void
    {
        foreach ([UserRole::Admin, UserRole::Lawyer, UserRole::User] as $role) {
            $user = User::factory()->create()->withVerifiedRole($role);

            $copy = unserialize(serialize($user));

            $this->assertInstanceOf(User::class, $copy);
            $this->assertSame($user->id, $copy->id);
            $this->assertSame($user->email, $copy->email);
            $this->assertNull($copy->verifiedRole());
            foreach (UserRole::cases() as $any) {
                $this->assertFalse($copy->hasRole($any), "unserialized user must not pass hasRole({$any->value})");
            }
        }
    }

    public function test_the_serialized_form_contains_no_trace_of_the_role(): void
    {
        $user = User::factory()->create()->withVerifiedRole(UserRole::Admin);

        $serialized = serialize($user);

        $this->assertStringNotContainsString('verifiedRole', $serialized);
        $this->assertStringNotContainsString('UserRole', $serialized);
        $this->assertStringNotContainsString('"admin"', $serialized);
        $this->assertStringContainsString($user->email, $serialized); // the user itself is still serialized
    }

    public function test_a_user_stored_in_a_cache_comes_back_without_a_role(): void
    {
        // By default this Laravel refuses to restore objects from a cache at all (cache.serializable_classes = false).
        // Worst case for this test: an application that allowed it.
        config(['cache.serializable_classes' => [User::class]]);

        $cache = Cache::build(['driver' => 'array', 'serialize' => true]); // stores serialized values, like file/redis
        $user = User::factory()->create()->withVerifiedRole(UserRole::Admin);

        $cache->put('someone', $user, 60);
        $restored = $cache->get('someone');

        $this->assertInstanceOf(User::class, $restored);
        $this->assertSame($user->id, $restored->id);
        $this->assertNull($restored->verifiedRole());
        $this->assertFalse($restored->hasRole(UserRole::Admin));
    }

    public function test_serializing_does_not_remove_the_role_from_the_live_user(): void
    {
        $user = User::factory()->create()->withVerifiedRole(UserRole::Lawyer);

        serialize($user);

        $this->assertSame(UserRole::Lawyer, $user->verifiedRole());
        $this->assertTrue($user->hasRole(UserRole::Lawyer));
    }
}
