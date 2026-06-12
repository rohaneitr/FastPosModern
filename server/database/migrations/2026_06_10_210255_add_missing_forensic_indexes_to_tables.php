<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $indexName]
        );
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public function up(): void
    {
        if ($this->tableExists('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!$this->indexExists('transactions', 'transactions_business_id_index')) {
                    $table->index('business_id');
                }
                if (!$this->indexExists('transactions', 'transactions_location_id_index') && Schema::hasColumn('transactions', 'location_id')) {
                    $table->index('location_id');
                }
            });
        }

        // Actual table name is 'transaction_lines' not 'transaction_items'
        if ($this->tableExists('transaction_lines')) {
            Schema::table('transaction_lines', function (Blueprint $table) {
                if (!$this->indexExists('transaction_lines', 'transaction_lines_product_id_index')) {
                    $table->index('product_id');
                }
            });
        }

        if ($this->tableExists('stock_ledgers')) {
            Schema::table('stock_ledgers', function (Blueprint $table) {
                if (!$this->indexExists('stock_ledgers', 'stock_ledgers_product_id_location_id_index') && Schema::hasColumn('stock_ledgers', 'location_id')) {
                    $table->index(['product_id', 'location_id']);
                }
            });
        }
    }

    public function down(): void
    {
        if ($this->tableExists('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndexIfExists('transactions_business_id_index');
                $table->dropIndexIfExists('transactions_location_id_index');
            });
        }

        if ($this->tableExists('transaction_lines')) {
            Schema::table('transaction_lines', function (Blueprint $table) {
                $table->dropIndexIfExists('transaction_lines_product_id_index');
            });
        }

        if ($this->tableExists('stock_ledgers')) {
            Schema::table('stock_ledgers', function (Blueprint $table) {
                $table->dropIndexIfExists('stock_ledgers_product_id_location_id_index');
            });
        }
    }
};
