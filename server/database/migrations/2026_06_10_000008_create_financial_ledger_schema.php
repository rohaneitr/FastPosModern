<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Chart of Accounts (COA)
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('code')->index(); // e.g., '1000' for Cash, '1200' for Inventory
            $table->string('name'); 
            $table->string('type'); // Asset, Liability, Equity, Revenue, Expense
            $table->boolean('is_system')->default(false); // Protects core accounts from deletion
            $table->timestamps();
            
            $table->unique(['business_id', 'code']);
        });

        // 2. Journal Entries (The Header)
        Schema::create('finance_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('reference_type'); // 'pos_sale', 'production_order', 'lab_test'
            $table->string('reference_id'); 
            $table->string('description')->nullable();
            $table->date('entry_date');
            $table->timestamps();
        });

        // 3. Journal Lines (The Debits and Credits)
        Schema::create('finance_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('finance_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('finance_accounts');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 15, 4); // High precision for fractional cents
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_journal_lines');
        Schema::dropIfExists('finance_journal_entries');
        Schema::dropIfExists('finance_accounts');
    }
};
