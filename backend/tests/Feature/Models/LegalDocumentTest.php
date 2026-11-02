<?php

namespace Tests\Feature\Models;

use App\Models\LegalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_valid_document(): void
    {
        $document = LegalDocument::factory()->create();

        $this->assertDatabaseHas('legal_documents', ['id' => $document->id, 'title' => $document->title]);
    }

    public function test_publication_year_is_cast_to_integer(): void
    {
        $document = LegalDocument::factory()->create(['publication_year' => 1972]);

        $this->assertSame(1972, $document->fresh()->publication_year);
    }

    public function test_in_category_scope_filters(): void
    {
        LegalDocument::factory()->create(['category' => 'Family Law']);
        LegalDocument::factory()->create(['category' => 'Labour Law']);

        $this->assertSame(1, LegalDocument::inCategory('Family Law')->count());
    }

    public function test_search_scope_matches_title_and_description_case_insensitively(): void
    {
        LegalDocument::factory()->create(['title' => 'Marriage Registration', 'description' => 'x']);
        LegalDocument::factory()->create(['title' => 'Other', 'description' => 'About inheritance']);
        LegalDocument::factory()->create(['title' => 'Unrelated', 'description' => 'nothing']);

        $this->assertSame(1, LegalDocument::search('marriage')->count());
        $this->assertSame(1, LegalDocument::search('INHERITANCE')->count());
        $this->assertSame(3, LegalDocument::search('   ')->count());
    }

    public function test_only_listed_fields_are_mass_assignable(): void
    {
        $document = new LegalDocument(['title' => 'T', 'id' => 99, 'created_at' => '2000-01-01']);

        $this->assertSame('T', $document->title);
        $this->assertNull($document->id);
        $this->assertNull($document->created_at);
    }
}
