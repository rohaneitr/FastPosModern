<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Attempt to create the pg_trgm extension
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
            Log::info('pg_trgm extension successfully ensured.');
        } catch (\Exception $e) {
            Log::warning('Failed to create pg_trgm extension. This may require superuser privileges. Continuing without extension creation.', ['error' => $e->getMessage()]);
        }

        // 2. Add GIN indexes
        // The businesses.name index
        DB::statement('CREATE INDEX IF NOT EXISTS businesses_name_trgm_idx ON businesses USING GIN (name gin_trgm_ops);');
        
        // The users.first_name and last_name indexes
        DB::statement('CREATE INDEX IF NOT EXISTS users_first_name_trgm_idx ON users USING GIN (first_name gin_trgm_ops);');
        DB::statement('CREATE INDEX IF NOT EXISTS users_last_name_trgm_idx ON users USING GIN (last_name gin_trgm_ops);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS businesses_name_trgm_idx;');
        DB::statement('DROP INDEX IF EXISTS users_first_name_trgm_idx;');
        DB::statement('DROP INDEX IF EXISTS users_last_name_trgm_idx;');
        
        // We typically do NOT drop extensions in down() as they may be used by other parts of the system
        // DB::statement('DROP EXTENSION IF EXISTS pg_trgm;');
    }
};
