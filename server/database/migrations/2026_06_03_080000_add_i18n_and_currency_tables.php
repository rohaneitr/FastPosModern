<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Currencies Master Table
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // ISO 4217
            $table->string('name');
            $table->string('symbol', 10);
            $table->string('symbol_native', 10)->nullable();
            $table->unsignedTinyInteger('decimal_digits')->default(2);
            $table->string('name_bn')->nullable(); // Bengali name
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Exchange Rates Table
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3)->default('USD');
            $table->string('target_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->string('source')->default('manual'); // 'manual', 'api'
            $table->timestamps();
            $table->unique(['base_currency', 'target_currency']);
        });

        // 3. Add currency/language settings to businesses
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('language', 7)->default('en')->after('currency_code');
            $table->string('currency_symbol_position', 10)->default('before')->after('language');
            $table->unsignedTinyInteger('currency_decimal_precision')->default(2)->after('currency_symbol_position');
            $table->string('currency_thousands_separator', 1)->default(',')->after('currency_decimal_precision');
            $table->string('currency_decimal_separator', 1)->default('.')->after('currency_thousands_separator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'language',
                'currency_symbol_position',
                'currency_decimal_precision',
                'currency_thousands_separator',
                'currency_decimal_separator',
            ]);
        });
    }
};
