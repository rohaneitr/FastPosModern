<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // 'pos', 'restaurant', 'manufacturing', 'clinical'
            $table->string('name');
            $table->decimal('price_per_month', 10, 2)->default(0);
            $table->decimal('price_per_year', 10, 2)->default(0);
            $table->boolean('is_core')->default(false); // Can't be removed
            $table->timestamps();
        });

        Schema::create('tenant_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('invoice_number')->unique();
            $table->string('type'); // 'new_subscription', 'upgrade_prorated', 'renewal'
            $table->json('requested_modules'); // the delta modules
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('Pending'); // Pending, Paid, Failed
            $table->date('billing_cycle_start')->nullable();
            $table->date('billing_cycle_end')->nullable();
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->date('billing_due_date')->nullable();
            $table->string('subscription_status')->default('Active'); // Active, Grace_Period, Locked
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['billing_due_date', 'subscription_status']);
        });
        Schema::dropIfExists('tenant_invoices');
        Schema::dropIfExists('subscription_modules');
    }
};
