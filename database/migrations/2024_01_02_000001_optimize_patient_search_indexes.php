<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds indexes to support faster Patient Search queries.
 *
 * Addresses:
 * - Missing index on `phone` (searched by ILIKE)
 * - Missing composite index on (status, updated_at) — the default sort
 * - Missing index on `full_name` length — we add a trigram index via raw SQL
 *   so ILIKE '%term%' on full_name can use the index (PostgreSQL pg_trgm).
 *
 * Note: The trigram extension and GIN indexes are PostgreSQL-only.
 * They are silently skipped on SQLite (used for testing).
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            // Enable pg_trgm extension for trigram-based ILIKE on text columns
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            // Trigram indexes for ILIKE '%...%' searches
            DB::statement('CREATE INDEX IF NOT EXISTS patients_full_name_trgm ON patients USING gin (full_name gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS patients_card_number_trgm ON patients USING gin (card_number gin_trgm_ops)');
        }

        Schema::table('patients', function (Blueprint $table) {
            // phone is searched but has no index
            $table->index('phone', 'patients_phone_index');

            // Composite index for the default list query: WHERE status = 'Active' ORDER BY updated_at DESC
            $table->index(['status', 'updated_at'], 'patients_status_updated_at_index');
        });
    }

    public function down(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            DB::statement('DROP INDEX IF EXISTS patients_full_name_trgm');
            DB::statement('DROP INDEX IF EXISTS patients_card_number_trgm');
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_phone_index');
            $table->dropIndex('patients_status_updated_at_index');
        });
    }
};
