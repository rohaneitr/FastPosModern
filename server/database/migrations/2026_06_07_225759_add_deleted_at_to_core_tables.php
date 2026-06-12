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
        // Add soft deletes to businesses
        if (Schema::hasTable('businesses') && !Schema::hasColumn('businesses', 'deleted_at')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to purchases
        if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'deleted_at')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to transactions (sales)
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'deleted_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Ensure products have it
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
