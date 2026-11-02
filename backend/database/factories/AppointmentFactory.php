<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationType;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lawyer_id' => Lawyer::factory(),
            'scheduled_at' => now()->addDays(fake()->numberBetween(1, 30))->setTime(fake()->numberBetween(9, 16), 0),
            'duration_minutes' => 30,
            'consultation_type' => ConsultationType::InPerson,
            'status' => AppointmentStatus::Pending,
            'reason' => fake()->sentence(10),
            'notes' => null,
            'fee' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => AppointmentStatus::Confirmed]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Completed,
            'scheduled_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled in a test.',
        ]);
    }

    public function video(): static
    {
        return $this->state(fn () => ['consultation_type' => ConsultationType::Video]);
    }
}
