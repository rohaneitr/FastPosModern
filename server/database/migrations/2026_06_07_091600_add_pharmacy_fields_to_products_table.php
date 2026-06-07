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
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_medicine')) {
                $table->boolean('is_medicine')->default(false)->after('generic_name');
            }
            if (!Schema::hasColumn('products', 'unit_conversion_ratio')) {
                $table->integer('unit_conversion_ratio')->default(1)->after('is_medicine');
            }
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_lines', 'dosage_instructions')) {
                $table->string('dosage_instructions')->nullable()->after('item_tax');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_medicine', 'unit_conversion_ratio']);
        });
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropColumn('dosage_instructions');
        });
    }
};
