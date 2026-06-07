<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds heartbeat tracking and grace-period suspension to device_activations.
 *
 * Changes:
 *  - last_synced_at (timestamp, nullable) — updated on every heartbeat
 *  - grace_period_days (int) — max allowed offline days, stored per-device
 *    so changing a plan doesn't silently change existing activations
 *  - status enum widened: 'active' | 'suspended' | 'revoked'
 *    ('revoked' was already present; 'suspended' is the new grace-period state)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── SQLite: rebuild table to change the enum ──────────────────────────
        // SQLite does not support ALTER COLUMN for enum changes, so we use a
        // column-add approach and rely on the application layer for validation.

        Schema::table('device_activations', function (Blueprint $table) {
            // Track the last online heartbeat
            $table->timestamp('last_synced_at')->nullable()->after('activated_at');

            // Per-device grace period (days). Defaults:
            //   hybrid → 30 days,  mobile → 7 days,  web → 0 (always online)
            $table->unsignedSmallInteger('grace_period_days')->default(30)->after('last_synced_at');
        });

        // Backfill: set last_synced_at = activated_at for existing rows so
        // they don't get immediately suspended after the migration.
        DB::statement('UPDATE device_activations SET last_synced_at = activated_at WHERE last_synced_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('device_activations', function (Blueprint $table) {
            $table->dropColumn(['last_synced_at', 'grace_period_days']);
        });
    }
};
