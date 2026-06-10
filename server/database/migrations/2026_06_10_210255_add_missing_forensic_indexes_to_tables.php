<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes to high-frequency query paths if they don't already exist.
        // E.g., transaction checkout queries, stock ledger sweeps, etc.

        Schema::table('transactions', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('transactions');

            if (!array_key_exists('transactions_business_id_index', $indexesFound)) {
                $table->index('business_id');
            }
            if (!array_key_exists('transactions_location_id_index', $indexesFound)) {
                $table->index('location_id');
            }
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('transaction_items');

            if (!array_key_exists('transaction_items_product_id_index', $indexesFound)) {
                $table->index('product_id');
            }
        });

        Schema::table('stock_ledgers', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('stock_ledgers');

            if (!array_key_exists('stock_ledgers_product_id_location_id_index', $indexesFound)) {
                $table->index(['product_id', 'location_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['business_id']);
            $table->dropIndex(['location_id']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'location_id']);
        });
    }
};
