<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('opening_balance', 22, 4)->default(0)->after('credit_limit');
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            // Drop foreign key if it exists, it might be named transaction_payments_transaction_id_foreign
            $table->dropForeign(['transaction_id']);
            $table->foreignId('transaction_id')->nullable()->change();
            // Re-add foreign key constraint with cascade
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            
            $table->foreignId('contact_id')->nullable()->after('transaction_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('type')->default('payment')->after('amount'); // 'payment', 'refund'
        });
    }

    public function down(): void
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
            $table->dropColumn('type');
            
            $table->dropForeign(['transaction_id']);
            $table->foreignId('transaction_id')->nullable(false)->change();
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
