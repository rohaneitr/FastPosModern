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
            if (!Schema::hasColumn('products', 'generic_name')) {
                $table->string('generic_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'purchase_price')) {
                $table->decimal('purchase_price', 22, 4)->nullable()->after('generic_name');
            }
            if (!Schema::hasColumn('products', 'sell_price_inc_tax')) {
                $table->decimal('sell_price_inc_tax', 22, 4)->nullable()->after('purchase_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['generic_name', 'purchase_price', 'sell_price_inc_tax']);
        });
    }
};
