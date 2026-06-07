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
        Schema::table('product_serials', function (Blueprint $table) {
            $table->index('serial_number');
            $table->index('status');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('transaction_date');
            $table->index('status');
            $table->index('type');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index('mobile');
            $table->index('type');
        });
        
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->dropIndex(['serial_number']);
            $table->dropIndex(['status']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['transaction_date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['type']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['mobile']);
            $table->dropIndex(['type']);
        });
        
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
    }
};
