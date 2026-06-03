<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('enable_sr_no')->default(false)->after('sku')->comment('Enable IMEI or Serial Number tracking');
            $table->boolean('enable_warranty')->default(false)->after('enable_sr_no');
            $table->boolean('enable_expiry')->default(false)->after('enable_warranty')->comment('Enable Lot Expiry Tracking');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['enable_sr_no', 'enable_warranty', 'enable_expiry']);
        });
    }
};
