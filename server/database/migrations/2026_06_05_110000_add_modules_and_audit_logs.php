<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 – Two migrations in one file:
 *
 * 1. Add `enabled_modules` JSON column to businesses table.
 *    Stores per-tenant feature flags: { "hr": true, "inventory_sync": false, ... }
 *
 * 2. Create `audit_logs` table for SuperAdmin activity trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Per-tenant module flags ─────────────────────────────────────────
        Schema::table('businesses', function (Blueprint $table) {
            // Stored as JSON: { "advanced_hr": true, "inventory_sync": true, ... }
            // NULL means "use plan defaults" — all modules allowed.
            $table->json('enabled_modules')->nullable()->after('settings');
        });

        // ── 2. Audit log table ────────────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who performed the action (nullable for system-generated events)
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_type')->nullable();         // e.g. "App\Domain\IAM\Models\User"
            $table->string('causer_name')->nullable();         // denormalised for readability

            // What happened
            $table->string('event');                           // e.g. "tenant_approved"
            $table->string('description');                     // human-readable sentence
            $table->json('properties')->nullable();            // before/after or extra context

            // Subject — the entity this action was performed ON
            $table->string('subject_type')->nullable();        // e.g. "Business"
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();       // denormalised name/email

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
            // No updated_at — audit logs are immutable.

            $table->index(['causer_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('enabled_modules');
        });
    }
};
