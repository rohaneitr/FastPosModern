<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM DOMAIN
        
        // 1. Contacts (Customers and Suppliers)
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            
            $table->string('type'); // 'customer', 'supplier', 'both'
            $table->string('supplier_business_name')->nullable();
            
            $table->string('prefix')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->index(); // full name for quick search
            
            $table->string('email')->nullable();
            $table->string('contact_id')->nullable(); // custom user-facing ID
            $table->string('tax_number')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('mobile')->nullable();
            $table->string('landline')->nullable();
            
            $table->foreignId('customer_group_id')->nullable(); // Note: Needs customer_groups table if implemented
            
            $table->decimal('credit_limit', 22, 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
