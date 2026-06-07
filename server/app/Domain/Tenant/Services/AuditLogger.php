<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * AuditLogger  (Phase 4 — Audit Trail Service)
 * ─────────────────────────────────────────────────────────────────────────────
 * Central write point for all SuperAdmin audit events.
 *
 * Usage:
 *   AuditLogger::record(
 *       actor:       $request->user(),
 *       event:       'tenant_approved',
 *       description: "Tenant 'Acme Corp' approved and provisioned.",
 *       subject:     $business,
 *       properties:  ['plan' => 'Pro', 'license_key' => substr($key, 0, 20)],
 *   );
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AuditLogger
{
    /**
     * Record an audit event. Silently swallows exceptions so a logging
     * failure never breaks the primary business operation.
     *
     * @param  \App\Domain\IAM\Models\User|null $actor
     * @param  string                           $event       machine-readable key
     * @param  string                           $description human sentence
     * @param  Model|null                       $subject     entity acted upon
     * @param  array                            $properties  extra context / diff
     */
    public static function record(
        mixed   $actor,
        string  $event,
        string  $description,
        ?Model  $subject    = null,
        array   $properties = [],
    ): void {
        try {
            AuditLog::create([
                'causer_id'    => $actor?->id,
                'causer_type'  => $actor ? get_class($actor) : null,
                'causer_name'  => $actor
                    ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '') . ' <' . ($actor->email ?? '') . '>')
                    : 'System',
                'event'        => $event,
                'description'  => $description,
                'properties'   => $properties ?: null,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'subject_label'=> static::resolveLabel($subject),
                'ip_address'   => Request::ip(),
                'user_agent'   => substr(Request::userAgent() ?? '', 0, 512),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AuditLogger::record failed', [
                'event'     => $event,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    // ── Convenience wrappers ──────────────────────────────────────────────────

    public static function tenantApproved(mixed $actor, Model $business, string $planName, ?string $licenseKey = null): void
    {
        static::record(
            actor:       $actor,
            event:       'tenant_approved',
            description: "Tenant '{$business->name}' approved and provisioned on plan '{$planName}'.",
            subject:     $business,
            properties:  ['plan' => $planName, 'license_key_preview' => $licenseKey ? substr($licenseKey, 0, 30) . '…' : null],
        );
    }

    public static function tenantRejected(mixed $actor, mixed $request, string $reason): void
    {
        static::record(
            actor:       $actor,
            event:       'tenant_rejected',
            description: "Tenant request for '{$request->business_name}' rejected.",
            subject:     null,
            properties:  ['request_id' => $request->id, 'reason' => $reason],
        );
    }

    public static function licenseRevoked(mixed $actor, Model $license): void
    {
        static::record(
            actor:       $actor,
            event:       'license_revoked',
            description: "License for tenant #{$license->tenant_id} (plan #{$license->plan_id}) suspended/revoked.",
            subject:     $license,
            properties:  ['license_key_preview' => substr($license->license_key ?? '', 0, 30) . '…'],
        );
    }

    public static function modulesUpdated(mixed $actor, Model $business, array $before, array $after): void
    {
        static::record(
            actor:       $actor,
            event:       'modules_updated',
            description: "Module flags updated for tenant '{$business->name}'.",
            subject:     $business,
            properties:  ['before' => $before, 'after' => $after],
        );
    }

    public static function tenantDeleted(mixed $actor, string $businessName, int $businessId): void
    {
        static::record(
            actor:       $actor,
            event:       'tenant_deleted',
            description: "Tenant '{$businessName}' (ID: {$businessId}) permanently deleted.",
            subject:     null,
            properties:  ['business_id' => $businessId, 'business_name' => $businessName],
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function resolveLabel(?Model $subject): ?string
    {
        if (!$subject) return null;
        return $subject->name ?? $subject->business_name ?? $subject->email ?? ((string) $subject->getKey());
    }
}
