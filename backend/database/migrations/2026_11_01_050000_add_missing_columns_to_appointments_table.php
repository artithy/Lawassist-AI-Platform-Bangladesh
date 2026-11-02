<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original appointments migration (2026_11_01_040426) only created id + timestamps.
 * This migration ADDS the columns an appointment actually needs. It never drops, renames or rewrites
 * anything that already exists:
 *
 *  - every column is added only if it is not already there (Schema::hasColumn), so it can never create duplicates;
 *  - user_id and lawyer_id are NOT NULL foreign keys with ON DELETE RESTRICT: an appointment always has an owner
 *    and a lawyer, and a user or lawyer who still has appointments cannot be deleted (deactivate a lawyer with
 *    lawyers.is_available instead). A NOT NULL column cannot be added to a table that already has rows, so the
 *    migration stops with a clear message instead of failing half-way if it finds any;
 *  - PostgreSQL runs the whole migration in one transaction, so a failure leaves the table untouched.
 *
 * Target database: PostgreSQL (Supabase). On SQLite (the in-memory test database) SQLite cannot add a NOT NULL
 * column without a default, nor a foreign key to an existing table, so there the two columns are plain nullable
 * integers. The tests therefore cannot prove NOT NULL / RESTRICT; that is verified against PostgreSQL only.
 */
return new class extends Migration
{
    private const TABLE = 'appointments';

    public function up(): void
    {
        $addedUser = ! Schema::hasColumn(self::TABLE, 'user_id');
        $addedLawyer = ! Schema::hasColumn(self::TABLE, 'lawyer_id');
        $addedSchedule = ! Schema::hasColumn(self::TABLE, 'scheduled_at');
        $addedStatus = ! Schema::hasColumn(self::TABLE, 'status');

        if (($addedUser || $addedLawyer) && DB::table(self::TABLE)->exists()) {
            throw new RuntimeException(
                'appointments already contains rows, so NOT NULL user_id / lawyer_id columns cannot be added safely. '
                .'Nothing was changed. Back up and review the existing rows first.'
            );
        }

        // SQLite (tests only) cannot add a NOT NULL column without a default; PostgreSQL can on an empty table.
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table(self::TABLE, function (Blueprint $table) use ($addedUser, $addedLawyer, $addedSchedule, $addedStatus, $isSqlite) {
            if ($addedUser) {
                // The person who booked: local users.id (bigint). Supabase UUIDs live in users.supabase_user_id, not here.
                $column = $table->foreignId('user_id');
                $isSqlite ? $column->nullable() : $column->constrained('users')->restrictOnDelete();
            }
            if ($addedLawyer) {
                $column = $table->foreignId('lawyer_id');
                $isSqlite ? $column->nullable() : $column->constrained('lawyers')->restrictOnDelete();
            }
            if ($addedSchedule) {
                $table->timestamp('scheduled_at')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->default(30);
            }
            if (! Schema::hasColumn(self::TABLE, 'consultation_type')) {
                $table->string('consultation_type', 20)->default('in_person'); // in_person | video
            }
            if ($addedStatus) {
                $table->string('status', 20)->default('pending'); // pending | confirmed | completed | cancelled
            }
            if (! Schema::hasColumn(self::TABLE, 'reason')) {
                $table->text('reason')->nullable(); // what the client wants help with
            }
            if (! Schema::hasColumn(self::TABLE, 'notes')) {
                $table->text('notes')->nullable(); // the lawyer's private notes
            }
            if (! Schema::hasColumn(self::TABLE, 'fee')) {
                $table->decimal('fee', 10, 2)->nullable(); // lawyer's fee at booking time
            }
            if (! Schema::hasColumn(self::TABLE, 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
        });

        // Indexes only for columns created by this run, so a re-run or a hand-edited table never gets duplicates.
        if ($addedLawyer || $addedSchedule) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['lawyer_id', 'scheduled_at'], 'appointments_lawyer_id_scheduled_at_index');
            });
        }
        if ($addedUser || $addedSchedule) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['user_id', 'scheduled_at'], 'appointments_user_id_scheduled_at_index');
            });
        }
        if ($addedStatus) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('status', 'appointments_status_index');
            });
        }
    }

    /**
     * Drops the appointment columns listed below, and nothing else: id, created_at and updated_at are never touched.
     *
     * WARNING: it drops any column with these names that exists, WITHOUT knowing whether up() or someone else added
     * it, and it deletes the data in them. Never run migrate:rollback / migrate:fresh against Supabase once
     * appointments exist.
     */
    public function down(): void
    {
        foreach (['appointments_lawyer_id_scheduled_at_index', 'appointments_user_id_scheduled_at_index', 'appointments_status_index'] as $index) {
            if (Schema::hasIndex(self::TABLE, $index)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex($index));
            }
        }

        foreach (['user_id', 'lawyer_id'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column) {
                    $table->dropConstrainedForeignId($column);
                });
            }
        }

        $columns = array_values(array_filter(
            ['scheduled_at', 'duration_minutes', 'consultation_type', 'status', 'reason', 'notes', 'fee', 'cancelled_at', 'cancellation_reason'],
            fn (string $column) => Schema::hasColumn(self::TABLE, $column),
        ));

        if ($columns !== []) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
