<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create inventory_item_serials table to track stock of specific serials
        if (!Schema::hasTable('inventory_item_serials')) {
            Schema::create('inventory_item_serials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->string('serial_number')->index();
                $table->string('imei_number')->nullable()->index();
                $table->string('status')->default('Available'); // 'Available', 'Sold', 'Returned', 'Damaged'
                $table->foreignId('purchase_line_id')->nullable()->constrained('purchase_lines')->nullOnDelete();
                $table->foreignId('transaction_sell_line_id')->nullable()->constrained('transaction_lines')->nullOnDelete();
                $table->timestamps();
                
                $table->unique(['product_id', 'serial_number'], 'prod_serial_unique');
            });
        }

        // 2. Add warranty_duration to transaction_lines
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->string('warranty_duration')->nullable()->after('item_tax');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropColumn('warranty_duration');
        });
        
        Schema::dropIfExists('inventory_item_serials');
    }
};
