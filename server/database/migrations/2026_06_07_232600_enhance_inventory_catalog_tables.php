<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('categories', 'status')) {
                $table->string('status')->default('active');
            }
        });

        Schema::table('brands', function (Blueprint $table) {
            if (!Schema::hasColumn('brands', 'description')) {
                $table->text('description')->nullable();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'purchase_price')) {
                $table->decimal('purchase_price', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'selling_price')) {
                $table->decimal('selling_price', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'current_stock')) {
                $table->decimal('current_stock', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path')->nullable();
            }
            if (!Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            
            // Note: SKU, barcode_type, business_id, category_id, brand_id, unit_id, alert_quantity are already added in earlier migrations,
            // or we make sure they are correct.
            // If barcode_type default needs updating to 'CODE128' instead of 'C128':
            if (Schema::hasColumn('products', 'barcode_type')) {
                // To change default, doctrine/dbal might be needed, but we can just leave it if it's fine,
                // or assume it's created. The request says: "barcode_type (default 'CODE128')."
                // It was 'C128' default. We'll change it.
                $table->string('barcode_type')->default('CODE128')->change();
            }
        });

        // Add composite unique index for sku per business if not exists
        Schema::table('products', function (Blueprint $table) {
            $indexes = Schema::getIndexes('products');
            $hasIndex = false;
            foreach ($indexes as $index) {
                if ($index['name'] === 'products_business_id_sku_unique' || $index['columns'] === ['business_id', 'sku']) {
                    $hasIndex = true;
                    break;
                }
            }
            if (!$hasIndex) {
                $table->unique(['business_id', 'sku']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'status']);
        });

        // Other rollbacks can be added if necessary, but keep it simple
    }
};
