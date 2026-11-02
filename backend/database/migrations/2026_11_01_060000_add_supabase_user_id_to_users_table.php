<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a Supabase Auth user (UUID, the "sub" claim of a verified token) to a Laravel user (bigint users.id).
 *
 *  - users.id stays the bigint primary key and is what every foreign key (appointments.user_id, ...) points to;
 *  - supabase_user_id is a nullable, unique PostgreSQL uuid: existing users simply have NULL (PostgreSQL allows many
 *    NULLs in a unique index) and nothing existing is changed or rewritten;
 *  - the column is only added if it is missing.
 *
 * It is set only by the server after a token has been cryptographically verified (a later milestone), never from
 * request input.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'supabase_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('supabase_user_id')->nullable()->unique();
            });
        }
    }

    /**
     * Drops the supabase_user_id column (and its unique index) and nothing else.
     * It deletes the Supabase links stored in it, so do not roll this back once users are linked.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'supabase_user_id')) {
            return;
        }

        if (Schema::hasIndex('users', 'users_supabase_user_id_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_supabase_user_id_unique'));
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('supabase_user_id'));
    }
};
