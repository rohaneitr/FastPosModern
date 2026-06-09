<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('academic_year');
            $table->timestamps();
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('student_batches')->cascadeOnDelete();
            $table->decimal('monthly_fee', 22, 4)->default(0);
            $table->enum('status', ['Active', 'Paused', 'Dropped'])->default('Active');
            $table->timestamps();
        });

        Schema::create('student_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->integer('billing_month');
            $table->integer('billing_year');
            $table->string('fee_type')->default('Monthly Tuition');
            $table->decimal('amount', 22, 4);
            $table->enum('status', ['Unpaid', 'Paid', 'Void'])->default('Unpaid');
            $table->timestamps();

            // Absolute Database-Level Idempotency Lock
            $table->unique(['student_id', 'billing_month', 'billing_year', 'fee_type'], 'student_billing_idx');
        });

        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('student_batches')->cascadeOnDelete();
            $table->string('exam_title');
            $table->date('exam_date');
            $table->timestamps();
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('contacts')->cascadeOnDelete();
            $table->jsonb('marks_payload');
            $table->decimal('cumulative_gpa', 5, 2)->nullable();
            $table->string('grade_letter', 5)->nullable();
            $table->timestamps();
            
            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('student_invoices');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('student_batches');
    }
};
