<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add compound index for business_id + location_id where applicable
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->index(['product_id', 'location_id'], 'idx_product_stocks_prod_loc');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['business_id', 'location_id'], 'idx_transactions_biz_loc');
        });

        // Add GIN index for JSON active_modules if PostgreSQL
        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_tenant') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE businesses ALTER COLUMN active_modules TYPE jsonb USING active_modules::text::jsonb');
            \Illuminate\Support\Facades\DB::statement('CREATE INDEX idx_businesses_active_modules ON businesses USING GIN (active_modules jsonb_path_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_product_stocks_prod_loc');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_biz_loc');
        });

        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_tenant') {
            \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS idx_businesses_active_modules');
        }
    }
};
