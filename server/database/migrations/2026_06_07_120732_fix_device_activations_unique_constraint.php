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
        if (Schema::hasTable('device_activations')) {
            Schema::table('device_activations', function (Blueprint $table) {
                // Drop the old unique constraint
                $table->dropUnique(['license_key']);
                
                // Add the correct composite unique index
                $table->unique(['license_key', 'device_fingerprint']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_activations')) {
            Schema::table('device_activations', function (Blueprint $table) {
                $table->dropUnique(['license_key', 'device_fingerprint']);
                $table->unique(['license_key']);
            });
        }
    }
};
