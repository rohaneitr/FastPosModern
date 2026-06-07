<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds email contact fields to tenant_requests so the approval
 * controller knows where to send Welcome / Rejection emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_requests', function (Blueprint $table) {
            $table->string('applicant_email')->nullable()->after('business_name')
                ->comment('Email address of the tenant applicant — used for approval/rejection notifications');
            $table->string('applicant_name')->nullable()->after('applicant_email')
                ->comment('Display name of the applicant');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_requests', function (Blueprint $table) {
            $table->dropColumn(['applicant_email', 'applicant_name']);
        });
    }
};
