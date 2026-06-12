<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alter products table to add hardware_attributes if not exists
        if (!Schema::hasColumn('products', 'hardware_attributes')) {
            Schema::table('products', function (Blueprint $table) {
                $table->jsonb('hardware_attributes')->nullable()->after('type');
            });
        }

        Schema::create('builder_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_core_component')->default(false);
            $table->timestamps();
        });

        Schema::create('commercial_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->jsonb('build_payload');
            $table->decimal('total_price', 15, 2);
            $table->timestamp('valid_until');
            $table->enum('status', ['Draft', 'Sent', 'ConvertedToSale', 'Expired'])->default('Draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_quotations');
        Schema::dropIfExists('builder_categories');
        if (Schema::hasColumn('products', 'hardware_attributes')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('hardware_attributes');
            });
        }
    }
};
