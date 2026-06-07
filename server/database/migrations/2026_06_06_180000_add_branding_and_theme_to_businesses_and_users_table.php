<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'dashboard_logo')) {
                $table->string('dashboard_logo')->nullable();
            }
            if (!Schema::hasColumn('businesses', 'invoice_logo')) {
                $table->string('invoice_logo')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
            if (!Schema::hasColumn('users', 'theme_preference')) {
                $table->string('theme_preference')->default('system');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['dashboard_logo', 'invoice_logo']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'theme_preference']);
        });
    }
};
