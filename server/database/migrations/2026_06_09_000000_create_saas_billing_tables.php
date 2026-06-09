<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('plan_id')->default('basic');
            $table->enum('status', ['Active', 'Past_Due', 'Canceled'])->default('Active');
            $table->dateTime('valid_until');
            $table->timestamps();
        });

        Schema::create('saas_payment_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 22, 4);
            $table->string('payment_method');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_payment_ledgers');
        Schema::dropIfExists('saas_subscriptions');
    }
};
