<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('business_id')->nullable()->after('id')->index();
                $table->string('device_hash')->nullable()->after('user_agent')->index();
                $table->string('api_endpoint')->nullable()->after('event');
                $table->string('action')->nullable()->after('api_endpoint');

                $table->jsonb('before_state')->nullable()->after('properties');
                $table->jsonb('after_state')->nullable()->after('before_state');

                $table->string('checksum')->nullable()->after('after_state')
                      ->comment('SHA-256 hash of: id + event + before_state + after_state + causer_id');
            });

            // PostgreSQL rules to prevent tampering
            DB::unprepared('
                CREATE RULE prevent_audit_log_delete AS ON DELETE TO audit_logs DO INSTEAD NOTHING;
                CREATE RULE prevent_audit_log_update AS ON UPDATE TO audit_logs DO INSTEAD NOTHING;
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            DB::unprepared('
                DROP RULE IF EXISTS prevent_audit_log_delete ON audit_logs;
                DROP RULE IF EXISTS prevent_audit_log_update ON audit_logs;
            ');

            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropColumn([
                    'business_id',
                    'device_hash',
                    'api_endpoint',
                    'action',
                    'before_state',
                    'after_state',
                    'checksum'
                ]);
            });
        }
    }
};
