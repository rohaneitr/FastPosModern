<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'status')) {
                $table->string('status')->default('active')->after('is_active');
            }
            if (!Schema::hasColumn('businesses', 'license_key')) {
                $table->string('license_key')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['status', 'license_key']);
        });
    }
};
