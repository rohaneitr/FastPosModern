<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'is_serialized')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_serialized')->default(false)->after('type');
                $table->integer('warranty_months')->default(0)->after('is_serialized');
            });
        }

        Schema::create('inventory_item_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('transaction_sell_line_id')->nullable();
            $table->string('serial_number');
            $table->enum('status', ['In_Stock', 'Sold', 'RMA_Claimed', 'Defective_Returned'])->default('In_Stock');
            $table->timestamps();

            // Clustered unique index for absolute tenant isolation and sub-millisecond retrieval
            $table->unique(['business_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_serials');
        if (Schema::hasColumn('products', 'is_serialized')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['is_serialized', 'warranty_months']);
            });
        }
    }
};
