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
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'max_users')) {
                $table->renameColumn('max_users', 'user_limit');
            } else if (!Schema::hasColumn('plans', 'user_limit')) {
                $table->integer('user_limit')->default(1);
            }

            if (Schema::hasColumn('plans', 'max_locations')) {
                $table->renameColumn('max_locations', 'location_limit');
            } else if (!Schema::hasColumn('plans', 'location_limit')) {
                $table->integer('location_limit')->default(1);
            }

            if (!Schema::hasColumn('plans', 'device_limit')) {
                $table->integer('device_limit')->default(1);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'user_limit')) {
                $table->renameColumn('user_limit', 'max_users');
            }
            if (Schema::hasColumn('plans', 'location_limit')) {
                $table->renameColumn('location_limit', 'max_locations');
            }
            if (Schema::hasColumn('plans', 'device_limit')) {
                $table->dropColumn('device_limit');
            }
        });
    }
};
