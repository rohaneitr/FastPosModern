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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('payment_method');
            $table->decimal('exchange_rate_used', 22, 4)->nullable()->after('currency_code');
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('credit_amount');
            $table->decimal('exchange_rate_used', 22, 4)->nullable()->after('currency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate_used']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate_used']);
        });
    }
};
