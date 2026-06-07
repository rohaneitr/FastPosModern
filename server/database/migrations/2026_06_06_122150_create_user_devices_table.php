<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('device_name')->nullable();
            $table->string('os')->nullable();
            $table->string('browser')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->string('status')->default('active'); // active, blocked
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
