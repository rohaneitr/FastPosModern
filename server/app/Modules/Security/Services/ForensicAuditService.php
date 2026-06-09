<?php

namespace App\Modules\Security\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class ForensicAuditService
{
    /**
     * Create an immutable forensic audit log entry.
     *
     * @param string $subjectType
     * @param int|string $subjectId
     * @param string $event
     * @param string $action
     * @param array|null $before
     * @param array|null $after
     * @param string $endpoint
     * @return void
     */
    public function snapshot(string $subjectType, $subjectId, string $event, string $action, ?array $before, ?array $after, string $endpoint): void
    {
        $userId = auth()->id();
        // Fallback or explicit resolution of business_id might be needed based on context
        $businessId = auth()->user()->business_id ?? null;
        $deviceHash = Request::header('X-Device-Hash', 'unknown');
        $ipAddress = Request::ip();
        $userAgent = Request::userAgent();

        // Convert exactly to JSON to preserve BigDecimal strings (don't let PHP round them)
        $beforeJson = $before ? json_encode($before) : null;
        $afterJson = $after ? json_encode($after) : null;

        // Generate tamper-evident checksum (excluding ID since we generate checksum BEFORE insert, 
        // but we can generate it using a UUID or just the data + timestamp)
        // Wait, to include the DB ID, we would have to insert, get ID, then update.
        // But updating is FORBIDDEN by our PostgreSQL rule!
        // So the checksum must be generated strictly from the data and a secret key.
        $timestamp = now()->toDateTimeString();
        $secret = config('app.key'); // system secret key
        $checksumPayload = $event . $action . $beforeJson . $afterJson . $userId . $timestamp . $secret;
        $checksum = hash('sha256', $checksumPayload);

        // Raw insert to ensure it bypasses any Eloquent restrictions or formatting changes
        DB::table('audit_logs')->insert([
            'business_id' => $businessId,
            'causer_id' => $userId,
            'causer_type' => $userId ? get_class(auth()->user()) : null,
            'causer_name' => $userId ? auth()->user()->name : 'System',
            'event' => $event,
            'action' => $action,
            'api_endpoint' => $endpoint,
            'description' => "{$action} performed on {$subjectType} ID {$subjectId}",
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before_state' => $beforeJson,
            'after_state' => $afterJson,
            'device_hash' => $deviceHash,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'checksum' => $checksum,
            'created_at' => $timestamp,
        ]);
    }
}
