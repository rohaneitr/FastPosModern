<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tax Rates
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 22, 4);
            $table->boolean('is_tax_group')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Receipt Printers
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('connection_type'); // network, windows, linux
            $table->string('capability_profile')->default('default');
            $table->string('char_per_line')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('port')->nullable();
            $table->string('path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // 3. Invoice Settings & Layouts
        Schema::create('invoice_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->text('header_text')->nullable();
            $table->string('invoice_heading')->nullable();
            $table->string('invoice_heading_not_paid')->nullable();
            $table->string('invoice_heading_paid')->nullable();
            $table->text('footer_text')->nullable();
            $table->boolean('show_business_name')->default(true);
            $table->boolean('show_location_name')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 4. Barcode Settings
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('width', 22, 4)->nullable();
            $table->decimal('height', 22, 4)->nullable();
            $table->decimal('paper_width', 22, 4)->nullable();
            $table->decimal('paper_height', 22, 4)->nullable();
            $table->decimal('top_margin', 22, 4)->nullable();
            $table->decimal('left_margin', 22, 4)->nullable();
            $table->decimal('row_distance', 22, 4)->nullable();
            $table->decimal('col_distance', 22, 4)->nullable();
            $table->integer('stickers_in_one_row')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 5. Customer Groups
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 22, 4);
            $table->string('price_calculation_type')->default('percentage'); // percentage or selling_price_group
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // 6. Warranties
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration');
            $table->string('duration_type'); // days, months, years
            $table->timestamps();
        });

        // 7. Selling Price Groups
        Schema::create('selling_price_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selling_price_groups');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('barcodes');
        Schema::dropIfExists('invoice_layouts');
        Schema::dropIfExists('printers');
        Schema::dropIfExists('tax_rates');
    }
};
