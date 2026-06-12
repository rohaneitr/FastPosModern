<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('purchase_line_id')->nullable();
            
            // Decimal tracking up to 4 places of precision for fractional inventory
            $table->decimal('original_qty', 22, 4);
            $table->decimal('remaining_qty', 22, 4);
            
            // Locked cost basis for this specific layer
            $table->decimal('unit_cost', 22, 4);
            
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            
            // Indexing for rapid chronological FIFO sorting
            $table->index(['business_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_layers');
    }
};
