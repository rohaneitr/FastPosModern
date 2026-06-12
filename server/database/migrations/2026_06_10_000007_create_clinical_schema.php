<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Patients Table (Strict HIPAA/GDPR Compliance)
        // PII fields are stored as text to accommodate Laravel's encrypted strings.
        Schema::create('clinical_patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('patient_uid')->unique(); // E.g., PAT-2026-0001
            
            // Encrypted PII Fields
            $table->text('first_name'); 
            $table->text('last_name');
            $table->text('mobile_number');
            $table->text('date_of_birth');
            $table->text('address')->nullable();
            
            // Non-PII Demographic Data
            $table->string('gender')->nullable();
            $table->string('blood_group')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Appointments
        Schema::create('clinical_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->foreignId('patient_id')->constrained('clinical_patients')->cascadeOnDelete();
            $table->unsignedBigInteger('doctor_id')->nullable()->index(); // Links to employees
            $table->timestamp('appointment_time');
            $table->string('status')->default('Scheduled'); // Scheduled, In-Progress, Completed, Cancelled
            $table->text('clinical_notes')->nullable(); // Encrypted at rest
            $table->timestamps();
        });

        // 3. Lab Orders (The Fulfillment Engine)
        Schema::create('clinical_lab_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('order_number')->unique();
            $table->foreignId('patient_id')->constrained('clinical_patients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('clinical_appointments')->nullOnDelete();
            
            // The Composite Product (e.g., CBC Blood Test)
            $table->unsignedBigInteger('product_id')->index(); 
            
            $table->string('status')->default('Sample_Collected'); // Sample_Collected, Processing, Result_Uploaded
            $table->json('parameters')->nullable(); // The dynamic schema of test parameters (e.g., { WBC: "8.5", RBC: "4.2" })
            $table->text('doctor_remarks')->nullable(); // Encrypted at rest
            
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_lab_orders');
        Schema::dropIfExists('clinical_appointments');
        Schema::dropIfExists('clinical_patients');
    }
};
