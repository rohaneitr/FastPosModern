<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // "ready" means all parts fulfilled. "pending_parts" means awaiting sourcing.
            $table->string('sourcing_status')->default('ready')->after('status');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            // "ready" means deducted. "pending_sourcing" means back-ordered.
            $table->string('sourcing_status')->default('ready')->after('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropColumn('sourcing_status');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('sourcing_status');
        });
    }
};
