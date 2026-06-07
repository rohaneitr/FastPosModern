<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->enum('plan_type', ['online_web', 'hybrid_offline_sync', 'mobile_native'])->default('online_web');
            $table->integer('device_limit')->default(1);
            $table->integer('employee_limit')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['plan_type', 'device_limit', 'employee_limit']);
        });
    }
};
