<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->index();
                $table->string('order_number')->unique();
                $table->unsignedBigInteger('product_id')->index(); // The Composite Finished Good
                $table->decimal('quantity', 10, 4); // Target Yield
                
                $table->string('status')->default('Draft'); // Draft, Scheduled, In-Progress, Completed, Cancelled
                
                // Financials (Overheads)
                $table->decimal('labor_cost', 15, 4)->default(0);
                $table->decimal('overhead_cost', 15, 4)->default(0);
                
                // Financials (Roll-up)
                $table->decimal('total_material_cost', 15, 4)->nullable(); // Populated on completion
                $table->decimal('total_production_cost', 15, 4)->nullable(); // Populated on completion
                
                $table->date('expiry_date')->nullable();
                
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
