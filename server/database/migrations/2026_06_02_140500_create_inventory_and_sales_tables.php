<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------
        // INVENTORY DOMAIN
        // -------------------------

        // 1. Locations (Stores, Warehouses)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('location_id')->nullable(); // Legacy reference
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Product Location Stocks
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('variations')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('qty_available', 22, 4)->default(0);
            $table->timestamps();
        });

        // -------------------------
        // SALES DOMAIN
        // -------------------------

        // 3. Transactions (Orders, Sales, Purchases)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('created_by')->constrained('users');
            
            $table->string('type'); // 'sell', 'purchase', 'opening_stock'
            $table->string('status'); // 'final', 'draft', 'pending'
            $table->boolean('is_quotation')->default(false);
            $table->string('invoice_no')->nullable();
            $table->dateTime('transaction_date');
            
            $table->decimal('total_before_tax', 22, 4)->default(0);
            $table->decimal('tax_amount', 22, 4)->default(0);
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->decimal('final_total', 22, 4)->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Transaction Lines (Items in an order)
        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variation_id')->nullable()->constrained('variations');
            
            $table->decimal('quantity', 22, 4)->default(0);
            $table->decimal('unit_price_before_discount', 22, 4)->default(0);
            $table->decimal('unit_price', 22, 4)->default(0); // after discount, before tax
            $table->decimal('unit_price_inc_tax', 22, 4)->default(0);
            $table->decimal('item_tax', 22, 4)->default(0);
            
            $table->timestamps();
        });

        // 5. Payments
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->decimal('amount', 22, 4)->default(0);
            $table->string('method'); // 'cash', 'card', 'bank_transfer'
            $table->string('payment_ref_no')->nullable();
            $table->dateTime('paid_on');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
        Schema::dropIfExists('transaction_lines');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('locations');
    }
};
