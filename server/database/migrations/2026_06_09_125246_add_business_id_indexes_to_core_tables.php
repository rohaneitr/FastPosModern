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
        $tables = ['users', 'products', 'transactions', 'purchases', 'contacts'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'business_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                    $indexesFound = $sm->listTableIndexes($table);
                    $indexName = "{$table}_business_id_index";

                    if (!array_key_exists($indexName, $indexesFound)) {
                        $tableBlueprint->index('business_id', $indexName);
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['users', 'products', 'transactions', 'purchases', 'contacts'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'business_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                    $indexesFound = $sm->listTableIndexes($table);
                    $indexName = "{$table}_business_id_index";

                    if (array_key_exists($indexName, $indexesFound)) {
                        $tableBlueprint->dropIndex($indexName);
                    }
                });
            }
        }
    }
};
