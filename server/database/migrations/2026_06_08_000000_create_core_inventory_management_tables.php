<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::statement('DROP TABLE IF EXISTS purchase_lines CASCADE');
        DB::statement('DROP TABLE IF EXISTS purchases CASCADE');
        DB::statement('DROP TABLE IF EXISTS contacts CASCADE');
        DB::statement('DROP TABLE IF EXISTS products CASCADE');
        DB::statement('DROP TABLE IF EXISTS units CASCADE');
        DB::statement('DROP TABLE IF EXISTS brands CASCADE');
        DB::statement('DROP TABLE IF EXISTS categories CASCADE');
        Schema::enableForeignKeyConstraints();

        // 1. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Brands
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 3. Units
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name');
            $table->boolean('allow_decimal')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        // 4. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->string('barcode_type')->default('CODE128');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->integer('alert_quantity')->default(0);
            $table->decimal('current_stock', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('image_path')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'sku']);
        });

        // 5. Contacts (Suppliers/Customers)
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type')->default('supplier');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // 6. Purchases
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('reference_no');
            $table->date('purchase_date');
            $table->string('status')->default('pending'); // 'pending', 'received'
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'reference_no']);
        });

        // 7. Purchase Lines
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('sub_total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
