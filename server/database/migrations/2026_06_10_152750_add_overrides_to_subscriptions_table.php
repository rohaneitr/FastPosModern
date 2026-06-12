<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'limit_overrides')) {
                $table->json('limit_overrides')->nullable();
            }
            if (!Schema::hasColumn('subscriptions', 'module_overrides')) {
                $table->json('module_overrides')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'limit_overrides')) {
                $table->dropColumn('limit_overrides');
            }
            if (Schema::hasColumn('subscriptions', 'module_overrides')) {
                $table->dropColumn('module_overrides');
            }
        });
    }
};
