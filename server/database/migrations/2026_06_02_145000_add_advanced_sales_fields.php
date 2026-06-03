<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add Commission Agents to Users
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_cmmsn_agent')->default(false)->after('business_id');
            $table->decimal('cmmsn_percent', 5, 2)->default(0)->after('is_cmmsn_agent');
        });

        // Add Advanced fields to Transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'commission_agent_id')) {
                $table->foreignId('commission_agent_id')->nullable()->constrained('users')->after('contact_id');
            }
            if (!Schema::hasColumn('transactions', 'return_parent_id')) {
                $table->foreignId('return_parent_id')->nullable()->constrained('transactions')->after('status')->comment('Links a sell_return to original sale');
            }
            $table->string('discount_type')->nullable()->after('discount_amount'); // fixed, percentage
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['commission_agent_id']);
            $table->dropForeign(['return_parent_id']);
            $table->dropColumn(['commission_agent_id', 'is_quotation', 'return_parent_id', 'discount_amount', 'discount_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_cmmsn_agent', 'cmmsn_percent']);
        });
    }
};
