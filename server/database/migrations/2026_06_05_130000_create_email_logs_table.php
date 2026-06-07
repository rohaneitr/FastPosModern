<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — Email audit log table.
 * Tracks every outbound email: to whom, subject, status, and error details.
 * Records are written by the EmailLogged event listener.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index()
                ->comment('business_id of the recipient tenant, if applicable');
            $table->string('to_email');
            $table->string('subject');
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->string('mailable_class')->nullable()
                ->comment('Fully-qualified Mailable class name for debugging');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            // Immutable — no updated_at

            $table->index(['to_email', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
