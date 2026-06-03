<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'branding')) {
                $table->json('branding')->nullable()->after('stripe_customer_id');
            }
        });

        // discount columns for transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'discount_amount')) {
                $table->decimal('discount_amount', 22, 4)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('transactions', 'discount_type')) {
                $table->string('discount_type')->nullable()->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('branding');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'discount_type']);
        });
    }
};
