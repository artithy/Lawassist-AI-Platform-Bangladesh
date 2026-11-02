<?php

namespace Tests\Feature\Models;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserSupabaseIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'supabase_user_id'));
    }

    public function test_uuid_round_trips_and_the_scope_finds_the_user(): void
    {
        $uuid = (string) Str::uuid();
        $user = User::factory()->create(['supabase_user_id' => $uuid]);
        User::factory()->create();

        $this->assertSame($uuid, $user->fresh()->supabase_user_id);
        $this->assertTrue(User::forSupabaseUser($uuid)->firstOrFail()->is($user));
        $this->assertNull(User::forSupabaseUser((string) Str::uuid())->first());
    }

    public function test_two_users_cannot_share_a_supabase_id(): void
    {
        $uuid = (string) Str::uuid();
        User::factory()->create(['supabase_user_id' => $uuid]);

        $this->expectException(QueryException::class);
        User::factory()->create(['supabase_user_id' => $uuid]);
    }

    public function test_many_users_can_be_unlinked(): void
    {
        User::factory()->unlinked()->count(3)->create();

        $this->assertSame(3, User::whereNull('supabase_user_id')->count());
    }

    public function test_supabase_id_cannot_be_mass_assigned(): void
    {
        $user = new User([
            'name' => 'A',
            'email' => 'a@example.com',
            'supabase_user_id' => (string) Str::uuid(),
        ]);

        $this->assertNull($user->supabase_user_id);
    }

    public function test_password_is_still_required(): void
    {
        // Supabase-provisioned users will get a random hash (a later milestone), not a NULL password.
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'No Password',
            'email' => 'nopassword@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_appointments_still_point_at_the_bigint_user_id_not_the_uuid(): void
    {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->for($user)->create()->fresh();

        $this->assertSame($user->id, $appointment->user_id);
        $this->assertIsInt($appointment->user_id);
        $this->assertNotSame($user->supabase_user_id, (string) $appointment->user_id);
    }
}
