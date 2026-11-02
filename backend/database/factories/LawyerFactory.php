<?php

namespace Database\Factories;

use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lawyer>
 */
class LawyerFactory extends Factory
{
    protected $model = Lawyer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null, // an admin links a login; see linkedTo()
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('01#########'),
            'specialization' => fake()->randomElement(['Family Law', 'Criminal Law', 'Land & Property', 'Labour Law', 'Corporate Law']),
            'experience_years' => fake()->numberBetween(1, 35),
            'bar_council_number' => fake()->unique()->numerify('BC-######'),
            'consultation_fee' => fake()->randomElement([500, 800, 1000, 1500, 2000]),
            'bio' => fake()->paragraph(),
            'profile_image' => null,
            'is_verified' => true,
            'is_available' => true,
        ];
    }

    /** A lawyer whose profile is linked to the given user (or a new one). */
    public function linkedTo(?User $user = null): static
    {
        return $this->state(fn () => ['user_id' => $user?->getKey() ?? User::factory()]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['is_verified' => false]);
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }
}
