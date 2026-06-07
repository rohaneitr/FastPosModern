<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('plans', 'enabled_modules')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->json('enabled_modules')->nullable();
            });
            DB::statement("UPDATE plans SET enabled_modules = '[]'");
        } else {
            DB::statement("UPDATE plans SET enabled_modules = '[]' WHERE enabled_modules LIKE 'O:%' OR enabled_modules IS NULL");
            Schema::table('plans', function (Blueprint $table) {
                $table->json('enabled_modules')->change();
            });
        }

        DB::statement("UPDATE plans SET features = '[]' WHERE features LIKE 'O:%' OR features IS NULL");

        Schema::table('plans', function (Blueprint $table) {
            $table->json('features')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->text('enabled_modules')->nullable()->change();
            $table->text('features')->nullable()->change();
        });
    }
};
