<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_table_has_every_column_the_model_needs(): void
    {
        $this->assertTrue(Schema::hasTable('appointments'));

        $this->assertTrue(Schema::hasColumns('appointments', [
            'id', 'user_id', 'lawyer_id', 'scheduled_at', 'duration_minutes', 'consultation_type',
            'status', 'reason', 'notes', 'fee', 'cancelled_at', 'cancellation_reason',
            'created_at', 'updated_at',
        ]));
    }

    public function test_each_column_exists_only_once(): void
    {
        $names = Schema::getColumnListing('appointments');

        $this->assertSame($names, array_values(array_unique($names)));
    }

    public function test_legal_documents_and_lawyers_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('legal_documents', ['title', 'category', 'content', 'publication_year']));
        $this->assertTrue(Schema::hasColumns('lawyers', ['email', 'specialization', 'consultation_fee', 'is_verified', 'is_available']));
    }

    public function test_identity_mapping_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'supabase_user_id'));
        $this->assertTrue(Schema::hasColumn('lawyers', 'user_id'));
    }
}
