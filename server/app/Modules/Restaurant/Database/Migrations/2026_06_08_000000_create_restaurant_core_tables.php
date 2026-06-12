<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('table_number');
            $table->integer('seating_capacity');
            $table->enum('status', ['Available', 'Occupied', 'Maintenance'])->default('Available');
            $table->timestamps();
        });

        Schema::create('restaurant_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('restaurant_tables')->cascadeOnDelete();
            $table->unsignedBigInteger('waiter_id'); // Weak reference to User table
            $table->enum('status', ['Active', 'Settled'])->default('Active');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('restaurant_kot_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('restaurant_sessions')->cascadeOnDelete();
            $table->string('ticket_number')->index();
            $table->enum('status', ['Pending', 'Preparing', 'Ready', 'Served'])->default('Pending');
            $table->jsonb('item_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_kot_tickets');
        Schema::dropIfExists('restaurant_sessions');
        Schema::dropIfExists('restaurant_tables');
    }
};
