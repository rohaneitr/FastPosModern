<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            
            // Basic settings
            $table->date('start_date')->nullable();
            $table->string('time_zone')->default('Asia/Kolkata');
            $table->string('currency_code', 3)->default('USD');
            $table->string('logo')->nullable();
            
            // Tax Information
            $table->string('tax_number_1', 100)->nullable();
            $table->string('tax_label_1', 10)->nullable();
            $table->string('tax_number_2', 100)->nullable();
            $table->string('tax_label_2', 10)->nullable();
            
            // Consolidate hundreds of legacy boolean/settings columns into a JSON column
            $table->json('settings')->nullable();
            
            // SaaS / Subscription fields
            $table->boolean('is_active')->default(true);
            $table->timestamp('subscription_expires_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
