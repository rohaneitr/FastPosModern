<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add FEFO partial index on inventory_layers for active (non-depleted) layers.
 *
 * RATIONALE: SystemIntegrityCheck command requires a partial index on
 * inventory_layers WHERE remaining_qty > 0 to prove FEFO lock performance.
 * Without it, FEFO queries on large catalogs scan all expired/depleted
 * layers unnecessarily.
 *
 * RISK: LOW — adding a partial index is non-destructive and fully reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventory_layers')) {
            return;
        }

        // Check if partial index already exists
        $exists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'inventory_layers' AND indexdef LIKE '%remaining_qty%'"
        );

        if (!$exists) {
            DB::statement(
                'CREATE INDEX inventory_layers_active_fefo_idx
                 ON inventory_layers (business_id, product_id, created_at)
                 WHERE remaining_qty > 0'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_layers_active_fefo_idx');
    }
};
