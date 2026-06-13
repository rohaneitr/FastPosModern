<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates a webhook event log for idempotent Stripe webhook processing.
 *
 * WHY A DEDICATED TABLE?
 * Stripe guarantees at-least-once delivery — the same event CAN be delivered
 * multiple times, especially during network failures or Stripe retries.
 * Without an idempotency guard, a single `invoice.payment_succeeded` event
 * received twice would activate a business twice (harmless), but a
 * `customer.subscription.deleted` would suspend it twice — creating a
 * forensic audit gap where the second write shadows the first.
 *
 * This table prevents that by recording each event ID BEFORE processing.
 * If the event ID already exists, we return 200 immediately (Stripe expects
 * 2xx to stop retrying) without touching the database again.
 *
 * The `processed_at` column + a weekly cleanup job keeps the table lean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();

            // Stripe's globally unique event ID (e.g. "evt_1AbCdEfGhIj...")
            // Indexed for O(1) duplicate detection.
            $table->string('stripe_event_id')->unique();

            // Stripe event type for audit trail (e.g. "invoice.payment_succeeded")
            $table->string('event_type', 100);

            // Outcome of processing: 'processed', 'failed', 'skipped'
            $table->string('status', 20)->default('processed');

            // business_id resolved during processing (null if business not found)
            $table->unsignedBigInteger('business_id')->nullable()->index();

            // ISO 8601 timestamp from the Stripe event object (for audit, not logic)
            $table->timestamp('stripe_created_at')->nullable();

            // When we finished processing
            $table->timestamp('processed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
