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
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('serial_number');
            $table->enum('status', ['available', 'sold', 'returned', 'in_warranty', 'rma'])->default('available');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions'); // when sold
            $table->foreignId('purchase_id')->nullable()->constrained('transactions'); // when purchased
            $table->dateTime('warranty_start_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};
