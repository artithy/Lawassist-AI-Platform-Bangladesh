<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a lawyer profile to the login (Laravel user) of that lawyer. The link is made by an ADMIN, never by
 * matching e-mail addresses.
 *
 *  - user_id is a nullable, unique bigint foreign key to users.id: many profiles can be unlinked (NULL), but one
 *    user can be linked to at most one lawyer profile;
 *  - ON DELETE SET NULL: deleting a user keeps the public lawyer profile and just unlinks it (an admin can link it
 *    again). Users who still have appointments cannot be deleted anyway (appointments.user_id is RESTRICT);
 *  - existing lawyer rows are untouched (they get NULL);
 *  - the column is only added if it is missing.
 *
 * On SQLite (tests only) the foreign key is not created (SQLite cannot add one to an existing table); the unique
 * index is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lawyers', 'user_id')) {
            Schema::table('lawyers', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Drops lawyers.user_id (its foreign key and unique index go with it) and nothing else.
     * It deletes the lawyer-to-login links stored in it.
     */
    public function down(): void
    {
        if (Schema::hasColumn('lawyers', 'user_id')) {
            Schema::table('lawyers', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        }
    }
};
