<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create device_activations table.
 *
 * This migration was missing from the codebase (original was lost).
 * Schema reverse-engineered from DeviceHeartbeatController usage and
 * the existing constraint-fix migration (2026_06_07_120732).
 *
 * NOTE: The fix migration drops/re-adds the unique constraint on
 * (license_key, device_fingerprint) — this migration creates it initially.
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.5
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('device_activations')) {
            Schema::create('device_activations', function (Blueprint $table) {
                $table->id();

                // Tenant isolation — every row is scoped to a business
                $table->unsignedBigInteger('business_id');
                $table->foreign('business_id')
                    ->references('id')
                    ->on('businesses')
                    ->onDelete('cascade');

                // License linkage (string FK — license_key is a natural key)
                $table->string('license_key');
                $table->index('license_key');

                // Hardware fingerprint (SHA-256 or similar hash, up to 512 chars)
                $table->string('device_fingerprint', 512)->nullable();

                // Status: active | revoked | pending
                $table->string('status', 20)->default('pending');
                $table->index(['business_id', 'status']); // for FIFO eviction queries

                // Timestamps for heartbeat and activation tracking
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();

                // Offline grace window (days). Defaults differ by device type.
                $table->unsignedSmallInteger('grace_period_days')->default(3);

                $table->timestamps();

                // Composite unique: one fingerprint per license
                // (matches the constraint fixed in 2026_06_07_120732)
                $table->unique(['license_key', 'device_fingerprint']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activations');
    }
};
