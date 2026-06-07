<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('References businesses.id after provisioning');
            $table->string('business_name');
            $table->enum('type', ['web', 'hybrid', 'mobile'])->default('web');
            $table->unsignedBigInteger('plan_id')->constrained('plans');
            $table->string('transaction_id')->nullable()->unique()->comment('Payment gateway transaction ID');
            $table->json('kyc_docs')->nullable()->comment('Uploaded KYC document references');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('Super Admin user ID');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_requests');
    }
};
