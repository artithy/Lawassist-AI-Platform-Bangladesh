<?php

namespace Tests\Feature\Models;

use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawyerTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts(): void
    {
        $lawyer = Lawyer::factory()->create([
            'experience_years' => 7,
            'consultation_fee' => 1500,
            'is_verified' => 1,
            'is_available' => 0,
        ])->fresh();

        $this->assertSame(7, $lawyer->experience_years);
        $this->assertSame('1500.00', $lawyer->consultation_fee);
        $this->assertTrue($lawyer->is_verified);
        $this->assertFalse($lawyer->is_available);
    }

    public function test_has_many_appointments(): void
    {
        $lawyer = Lawyer::factory()->create();
        Appointment::factory()->count(2)->for($lawyer)->create();
        Appointment::factory()->create(); // someone else's

        $this->assertCount(2, $lawyer->appointments);
    }

    public function test_scopes(): void
    {
        Lawyer::factory()->create(['specialization' => 'Family Law']);
        Lawyer::factory()->unverified()->create(['specialization' => 'Family Law']);
        Lawyer::factory()->unavailable()->create(['specialization' => 'Labour Law']);

        $this->assertSame(2, Lawyer::verified()->count());
        $this->assertSame(2, Lawyer::available()->count());
        $this->assertSame(2, Lawyer::specializing('Family Law')->count());
    }

    public function test_email_must_be_unique(): void
    {
        Lawyer::factory()->create(['email' => 'same@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Lawyer::factory()->create(['email' => 'same@example.com']);
    }

    public function test_belongs_to_a_user_only_after_an_admin_links_it(): void
    {
        $lawyer = Lawyer::factory()->create();
        $this->assertNull($lawyer->user);

        $user = User::factory()->create();
        $lawyer->linkToUser($user);

        $this->assertSame($user->id, $lawyer->fresh()->user_id);
        $this->assertTrue($lawyer->fresh()->user->is($user));
    }

    public function test_unlinking_removes_the_login(): void
    {
        $lawyer = Lawyer::factory()->linkedTo()->create();

        $lawyer->unlinkUser();

        $this->assertNull($lawyer->fresh()->user_id);
    }

    public function test_one_user_cannot_be_linked_to_two_lawyer_profiles(): void
    {
        $user = User::factory()->create();
        Lawyer::factory()->create()->linkToUser($user);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Lawyer::factory()->create()->linkToUser($user);
    }

    public function test_many_lawyers_can_be_unlinked(): void
    {
        Lawyer::factory()->count(3)->create();

        $this->assertSame(3, Lawyer::whereNull('user_id')->count());
    }

    public function test_user_id_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $lawyer = new Lawyer(['name' => 'A', 'user_id' => $user->id]);

        $this->assertNull($lawyer->user_id);
    }
}
