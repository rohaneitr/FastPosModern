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
            $indexes = Schema::getIndexes('device_activations');
            $hasOldIndex = collect($indexes)->contains('name', 'device_activations_license_key_unique');
            $hasNewIndex = collect($indexes)->contains('name', 'device_activations_license_key_device_fingerprint_unique');
            
            Schema::table('device_activations', function (Blueprint $table) use ($hasOldIndex, $hasNewIndex) {
                if ($hasOldIndex) {
                    $table->dropUnique('device_activations_license_key_unique');
                }
                
                if (!$hasNewIndex) {
                    $table->unique(['license_key', 'device_fingerprint']);
                }
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
