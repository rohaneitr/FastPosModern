<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add Expiry Date & Lot Number tracking for Advanced Inventory
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('qty_available');
            $table->string('lot_number')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropColumn(['expiry_date', 'lot_number']);
        });
    }
};
