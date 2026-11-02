<?php

namespace Database\Seeders;

use App\Models\Lawyer;
use Illuminate\Database\Seeder;

/**
 * Demo lawyers on the reserved example.com domain (they cannot be a real person's login).
 * Safe to run repeatedly (matched by email).
 */
class LawyerSeeder extends Seeder
{
    public function run(): void
    {
        $lawyers = [
            ['Demo Lawyer One', 'demo.lawyer1@example.com', 'Family Law', 12, 1000],
            ['Demo Lawyer Two', 'demo.lawyer2@example.com', 'Land & Property', 8, 1500],
            ['Demo Lawyer Three', 'demo.lawyer3@example.com', 'Criminal Law', 20, 2000],
            ['Demo Lawyer Four', 'demo.lawyer4@example.com', 'Labour Law', 5, 800],
        ];

        foreach ($lawyers as [$name, $email, $specialization, $years, $fee]) {
            Lawyer::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'specialization' => $specialization,
                    'experience_years' => $years,
                    'consultation_fee' => $fee,
                    'bio' => 'Demo profile for development only.',
                    'is_verified' => true,
                    'is_available' => true,
                ],
            );
        }
    }
}
