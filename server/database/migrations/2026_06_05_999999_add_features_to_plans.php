<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'features')) {
                $table->json('features')->nullable()->after('max_locations');
            }
        });

        // Seed default plan features
        DB::table('plans')->where('name', 'Basic')->update(['features' => json_encode(['pos', 'inventory'])]);
        DB::table('plans')->where('name', 'Pro')->update(['features' => json_encode(['pos', 'inventory', 'hr', 'quotations'])]);
        DB::table('plans')->where('name', 'Enterprise')->update(['features' => json_encode(['pos', 'inventory', 'hr', 'accounting', 'quotations', 'warranty'])]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'features')) {
                $table->dropColumn('features');
            }
        });
    }
};
