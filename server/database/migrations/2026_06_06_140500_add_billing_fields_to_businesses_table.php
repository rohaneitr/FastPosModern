<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'subscription_ends_at')) {
                $table->date('subscription_ends_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('businesses', 'subscription_status')) {
                $table->string('subscription_status')->default('Active')->after('subscription_ends_at'); // Active, Expired, Suspended
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['subscription_ends_at', 'subscription_status']);
        });
    }
};
