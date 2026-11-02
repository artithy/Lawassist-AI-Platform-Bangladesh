<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Demo content is for development/testing only; never put it in production.
        if (! app()->isProduction()) {
            $this->call([
                LegalDocumentSeeder::class,
                LawyerSeeder::class,
            ]);
        }
    }
}
