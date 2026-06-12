<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('code', 50)->index();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])->index();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            
            $table->unique(['business_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            
            // Link to the underlying transaction/purchase
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            
            $table->string('reference_number')->index();
            $table->date('date')->index();
            $table->text('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->enum('type', ['debit', 'credit']);
            // Precision 22,4 matching our BigDecimal
            $table->decimal('amount', 22, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};
