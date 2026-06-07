<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('employees');

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('base_salary', 22, 4)->default(0);
            $table->date('joining_date')->nullable();
            $table->string('designation')->nullable();
            $table->string('nid_number')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->string('status')->default('Present'); // Present, Late, Absent, Half-Day
            $table->timestamps();
            
            $table->unique(['user_id', 'date']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference_no')->nullable();
            $table->string('month'); // e.g., '2026-05'
            $table->decimal('base_salary', 22, 4)->default(0);
            $table->integer('total_working_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->decimal('gross_salary', 22, 4)->default(0);
            $table->decimal('bonus_commission', 22, 4)->default(0);
            $table->decimal('deductions_fines', 22, 4)->default(0);
            $table->decimal('net_salary', 22, 4)->default(0);
            $table->string('payment_status')->default('due'); // 'paid', 'due'
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employee_profiles');
    }
};
