<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_number')->index();
            $table->decimal('quantity_available', 10, 4)->default(0);
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->index();
            $table->timestamps();
        });

        Schema::create('pharmacy_batch_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('pharmacy_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('transaction_line_id'); // Weak reference to decoupled core
            $table->decimal('quantity_deducted', 10, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_batch_transactions');
        Schema::dropIfExists('pharmacy_batches');
    }
};
