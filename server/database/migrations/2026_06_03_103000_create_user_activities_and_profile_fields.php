<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('last_name');
            $table->string('timezone')->default('UTC')->after('language');
            $table->boolean('two_factor_enabled')->default(false)->after('timezone');
            $table->json('preferences')->nullable()->after('settings');
        });

        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activities');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'timezone', 'two_factor_enabled', 'preferences']);
        });
    }
};
