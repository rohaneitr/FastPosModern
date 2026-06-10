<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Hardware Specifications (Constraints Engine)
        Schema::create('hardware_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('component_type')->index(); // 'cpu', 'motherboard', 'ram', 'gpu', 'psu', 'case', 'storage'
            $table->string('socket_type')->nullable(); // 'LGA1700', 'AM5'
            $table->string('memory_type')->nullable(); // 'DDR4', 'DDR5'
            $table->string('form_factor')->nullable(); // 'ATX', 'Micro-ATX'
            $table->integer('wattage')->nullable(); // For PSUs or estimated TDP
            $table->timestamps();
        });

        // 2. Bill of Materials (BOM) / Product Assemblies
        Schema::create('product_assemblies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('child_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 4)->default(1);
            $table->timestamps();
            
            // A parent cannot have the same child multiple times (it should just increase quantity)
            $table->unique(['parent_product_id', 'child_product_id']);
        });

        // Add 'type' column to products if not exists
        if (!Schema::hasColumn('products', 'type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('type')->default('standard')->after('sku'); // 'standard', 'composite' (BOM)
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
        Schema::dropIfExists('product_assemblies');
        Schema::dropIfExists('hardware_specs');
    }
};
