<?php

namespace Tests\Feature\Models;

use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_appointments(): void
    {
        $user = User::factory()->create();
        Appointment::factory()->count(3)->for($user)->create();
        Appointment::factory()->create();

        $this->assertCount(3, $user->appointments);
    }

    public function test_an_admin_links_a_lawyer_profile_to_a_user(): void
    {
        $user = User::factory()->create();
        $lawyer = Lawyer::factory()->create();

        $lawyer->linkToUser($user);

        $this->assertTrue($user->fresh()->lawyerProfile->is($lawyer));
        $this->assertTrue($lawyer->fresh()->user->is($user));
    }

    public function test_user_without_a_linked_profile_has_no_lawyer_profile(): void
    {
        $user = User::factory()->create();
        Lawyer::factory()->create();

        $this->assertNull($user->lawyerProfile);
    }

    public function test_matching_email_addresses_never_link_a_user_to_a_lawyer(): void
    {
        $user = User::factory()->create(['email' => 'same@example.com']);
        $lawyer = Lawyer::factory()->create(['email' => 'same@example.com']);

        $this->assertNull($user->lawyerProfile);
        $this->assertNull($lawyer->user);
    }

    public function test_a_lawyer_sees_appointments_assigned_to_them_not_ones_they_booked_as_a_client(): void
    {
        $lawyerUser = User::factory()->create();
        $lawyer = Lawyer::factory()->linkedTo($lawyerUser)->create();

        Appointment::factory()->for($lawyer)->create();           // assigned to them
        Appointment::factory()->for($lawyerUser)->create();       // they booked it with another lawyer

        $this->assertTrue($lawyerUser->lawyerProfile->is($lawyer));
        $this->assertSame(1, Appointment::forLawyer($lawyerUser->lawyerProfile)->count());
        $this->assertSame(1, Appointment::forUser($lawyerUser)->count());
    }
}
