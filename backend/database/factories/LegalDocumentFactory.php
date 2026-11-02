<?php

namespace Database\Factories;

use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocument>
 */
class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(4), '.'),
            'category' => fake()->randomElement(['Constitutional Law', 'Family Law', 'Land & Property', 'Labour Law', 'Criminal Law']),
            'description' => fake()->paragraph(),
            'content' => fake()->paragraphs(4, true),
            'source' => fake()->optional()->company(),
            'publication_year' => fake()->optional()->numberBetween(1900, 2025),
        ];
    }
}
