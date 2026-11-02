<?php

namespace Tests\Feature\Models;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationType;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_a_user_and_a_lawyer(): void
    {
        $user = User::factory()->create();
        $lawyer = Lawyer::factory()->create();
        $appointment = Appointment::factory()->for($user)->for($lawyer)->create();

        $this->assertTrue($appointment->user->is($user));
        $this->assertTrue($appointment->lawyer->is($lawyer));
    }

    public function test_casts(): void
    {
        $appointment = Appointment::factory()->video()->create(['fee' => 1000])->fresh();

        $this->assertInstanceOf(Carbon::class, $appointment->scheduled_at);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame(ConsultationType::Video, $appointment->consultation_type);
        $this->assertSame(30, $appointment->duration_minutes);
        $this->assertSame('1000.00', $appointment->fee);
    }

    public function test_ends_at_adds_the_duration(): void
    {
        $appointment = Appointment::factory()->make([
            'scheduled_at' => '2030-01-01 10:00:00',
            'duration_minutes' => 45,
        ]);

        $this->assertSame('2030-01-01 10:45:00', $appointment->endsAt()->format('Y-m-d H:i:s'));
    }

    public function test_scopes_for_user_and_lawyer(): void
    {
        $user = User::factory()->create();
        $lawyer = Lawyer::factory()->create();

        Appointment::factory()->for($user)->for($lawyer)->create();
        Appointment::factory()->for($user)->create();
        Appointment::factory()->create();

        $this->assertSame(2, Appointment::forUser($user)->count());
        $this->assertSame(2, Appointment::forUser($user->id)->count());
        $this->assertSame(1, Appointment::forLawyer($lawyer)->count());
        $this->assertSame(1, Appointment::forUser($user)->forLawyer($lawyer)->count());
    }

    public function test_active_scope_excludes_completed_and_cancelled(): void
    {
        Appointment::factory()->create();             // pending
        Appointment::factory()->confirmed()->create();
        Appointment::factory()->completed()->create();
        Appointment::factory()->cancelled()->create();

        $this->assertSame(2, Appointment::active()->count());
        $this->assertTrue(Appointment::query()->where('status', 'cancelled')->first()->isCancelled());
    }

    public function test_upcoming_scope(): void
    {
        Appointment::factory()->create(['scheduled_at' => now()->addDay()]);
        Appointment::factory()->create(['scheduled_at' => now()->subDay()]);

        $this->assertSame(1, Appointment::upcoming()->count());
    }

    public function test_owner_status_fee_and_notes_cannot_be_mass_assigned(): void
    {
        $other = User::factory()->create();
        $lawyer = Lawyer::factory()->create();

        $appointment = new Appointment([
            'lawyer_id' => $lawyer->id,
            'scheduled_at' => '2030-01-01 10:00:00',
            'reason' => 'Need advice',
            // none of these may come from a request:
            'user_id' => $other->id,
            'status' => 'completed',
            'fee' => 1,
            'notes' => 'x',
            'cancelled_at' => now(),
        ]);

        $this->assertSame($lawyer->id, $appointment->lawyer_id);
        $this->assertSame('Need advice', $appointment->reason);
        $this->assertNull($appointment->user_id);
        $this->assertNull($appointment->status);
        $this->assertNull($appointment->fee);
        $this->assertNull($appointment->notes);
        $this->assertNull($appointment->cancelled_at);
    }
}
