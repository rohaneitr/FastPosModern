<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->boolean('is_in_transit')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->dropColumn(['location_id', 'is_in_transit']);
        });
    }
};
