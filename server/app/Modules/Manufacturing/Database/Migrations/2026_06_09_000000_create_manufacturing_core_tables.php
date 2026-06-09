<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('reference_no')->unique();
            $table->foreignId('finished_product_id')->constrained('products');
            $table->decimal('quantity_planned', 22, 4);
            $table->decimal('quantity_produced', 22, 4)->default(0.0000);
            $table->decimal('overhead_cost', 22, 4)->default(0.0000);
            $table->decimal('final_unit_cost', 22, 4)->nullable();
            $table->enum('status', ['Planning', 'Processing', 'Assembled', 'Finished'])->default('Planning');
            $table->timestamps();
        });

        Schema::create('production_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('products');
            $table->decimal('quantity_required', 22, 4);
            $table->decimal('quantity_consumed', 22, 4)->default(0.0000);
            $table->decimal('accumulated_cost', 22, 4)->default(0.0000);
            $table->timestamps();
        });

        Schema::create('production_scrap_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('products');
            $table->decimal('expected_wastage_qty', 22, 4)->default(0.0000);
            $table->decimal('actual_scrapped_qty', 22, 4);
            $table->decimal('scrapped_financial_value', 22, 4)->default(0.0000);
            $table->string('variance_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_scrap_logs');
        Schema::dropIfExists('production_order_lines');
        Schema::dropIfExists('production_orders');
    }
};
