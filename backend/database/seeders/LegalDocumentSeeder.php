<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Small, clearly-labelled demo content. Safe to run repeatedly (matched by title).
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            ['Constitutional Law', 'Fundamental rights: an overview', 'A plain-language overview of the fundamental rights guaranteed to citizens.'],
            ['Family Law', 'Marriage registration: what to know', 'A short guide to registering a marriage and the documents involved.'],
            ['Land & Property', 'Buying land: checking the title', 'Steps for checking ownership records before buying land.'],
            ['Labour Law', 'Employee rights at work', 'Basic rights around wages, working hours and termination.'],
            ['Criminal Law', 'If you are arrested: your rights', 'What a person can expect and ask for after an arrest.'],
        ];

        foreach ($documents as [$category, $title, $description]) {
            LegalDocument::query()->firstOrCreate(
                ['title' => $title],
                [
                    'category' => $category,
                    'description' => $description,
                    'content' => "DEMO CONTENT. {$description}\n\nThis text is sample data for development only and is not legal advice.",
                    'source' => 'Demo data',
                ],
            );
        }
    }
}
