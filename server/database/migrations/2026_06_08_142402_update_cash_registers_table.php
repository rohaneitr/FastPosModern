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
        Schema::dropIfExists('cash_registers');

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('device_hash');
            $table->unsignedBigInteger('opened_by_user_id');
            $table->enum('status', ['open', 'suspending', 'closed'])->default('open');
            $table->decimal('opening_balance', 22, 4)->default(0);
            $table->decimal('closing_balance_expected', 22, 4)->nullable();
            $table->decimal('closing_balance_counted', 22, 4)->nullable();
            $table->decimal('discrepancy_amount', 22, 4)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('opened_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
