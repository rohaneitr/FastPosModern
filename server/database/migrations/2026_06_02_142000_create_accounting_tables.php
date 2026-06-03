<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------
        // ACCOUNTING & EXPENSES
        // -------------------------

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories');
            $table->foreignId('created_by')->constrained('users');
            
            $table->string('reference_no')->nullable();
            $table->dateTime('expense_date');
            $table->decimal('total_amount', 22, 4)->default(0);
            $table->text('note')->nullable();
            
            $table->string('payment_status')->default('due'); // 'paid', 'due', 'partial'
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
