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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique()->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert initial modules
        DB::table('modules')->insert([
            ['name' => 'Core POS', 'slug' => 'core-pos', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Multi Currency', 'slug' => 'multi-currency', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Advanced RMA', 'slug' => 'advanced-rma', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'CRM Loyalty', 'slug' => 'crm-loyalty', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
