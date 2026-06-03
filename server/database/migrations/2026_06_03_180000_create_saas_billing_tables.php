<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stripe_price_id')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('interval')->default('month'); // month, year
            $table->integer('max_users')->default(1);
            $table->integer('max_locations')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status')->default('trialing'); // active, cancelled, past_due, trialing
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('stripe_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->timestamps();
        });
        
        // Insert default plans
        DB::table('plans')->insert([
            [
                'name' => 'Basic',
                'price' => 29.00,
                'interval' => 'month',
                'max_users' => 2,
                'max_locations' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pro',
                'price' => 79.00,
                'interval' => 'month',
                'max_users' => 10,
                'max_locations' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise',
                'price' => 199.00,
                'interval' => 'month',
                'max_users' => 999, // unlimited
                'max_locations' => 999,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
        
        // Subscribe existing businesses to Basic trial
        $businesses = DB::table('businesses')->get();
        foreach($businesses as $b) {
            DB::table('subscriptions')->insert([
                'business_id' => $b->id,
                'plan_id' => 1, // Basic
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
