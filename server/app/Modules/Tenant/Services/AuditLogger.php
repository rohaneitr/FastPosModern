<?php

namespace App\Modules\Tenant\Services;

use App\Modules\SuperAdmin\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Helper to extract impersonator context from the current token
     */
    protected static function getImpersonatorId($passedUser = null)
    {
        $user = $passedUser ?? auth()->user();
        if ($user && $user->currentAccessToken()) {
            $abilities = $user->currentAccessToken()->abilities ?? [];
            if (is_array($abilities) && in_array('impersonate', $abilities)) {
                foreach ($abilities as $ability) {
                    if (str_starts_with($ability, 'admin_id:')) {
                        return (int) str_replace('admin_id:', '', $ability);
                    }
                }
            }
        } else if (app()->runningInConsole() && !app()->runningUnitTests()) {
            // Mocked token retrieval for CLI
            return $user && isset($user->impersonator_id) ? $user->impersonator_id : null;
        }
        return null;
    }

    protected static function log($businessId, $user, $event, $type, $id, $old = [], $new = [])
    {
        // Don't log if running in console unless specified
        // Don't log if running in console unless specified
        if (app()->runningInConsole() && !app()->runningUnitTests() && !isset($_SERVER['argv'][1]) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] !== 'test:impersonation-audit' && app()->runningInConsole() && !app()->runningUnitTests())) {
            return;
        }

        AuditLog::create([
            'business_id' => $businessId,
            'user_id' => $user ? $user->id : null,
            'impersonator_id' => self::getImpersonatorId($user),
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => empty($old) ? null : $old,
            'new_values' => empty($new) ? null : $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function tenantApproved($user, $business, $planName, $licenseKey)
    {
        self::log(
            $business->id,
            $user,
            'tenant_approved',
            'App\Modules\Tenant\Models\Business',
            $business->id,
            [],
            ['plan' => $planName, 'license' => $licenseKey]
        );
    }

    public static function tenantRejected($user, $tenantRequest, $reason)
    {
        self::log(
            null,
            $user,
            'tenant_rejected',
            'App\Modules\Tenant\Models\TenantRequest',
            $tenantRequest->id,
            [],
            ['reason' => $reason]
        );
    }

    public static function tenantDeleted($user, $businessName, $businessId)
    {
        self::log(
            $businessId,
            $user,
            'tenant_deleted',
            'App\Modules\Tenant\Models\Business',
            $businessId,
            ['name' => $businessName],
            []
        );
    }

    public static function licenseRevoked($user, $license)
    {
        self::log(
            $license->tenant_id,
            $user,
            'license_revoked',
            'App\Modules\Tenant\Models\License',
            $license->id,
            ['status' => 'active'],
            ['status' => 'suspended']
        );
    }

    public static function subscriptionRenewed($user, $subscription, $oldEnd, $newEnd, $period)
    {
        self::log(
            $subscription->business_id,
            $user,
            'subscription_renewed',
            'App\Modules\Tenant\Models\Subscription',
            $subscription->id,
            ['current_period_end' => $oldEnd],
            ['current_period_end' => $newEnd, 'extension_period' => $period]
        );
    }

    public static function subscriptionStatusOverridden($user, $subscription, $oldStatus, $newStatus)
    {
        self::log(
            $subscription->business_id,
            $user,
            'subscription_status_overridden',
            'App\Modules\Tenant\Models\Subscription',
            $subscription->id,
            ['status' => $oldStatus],
            ['status' => $newStatus, 'message' => "Subscription status overridden from {$oldStatus} to {$newStatus}"]
        );
    }

    public static function subscriptionCapabilitiesModified($user, $subscription, $limitOverrides, $moduleOverrides)
    {
        self::log(
            $subscription->business_id,
            $user,
            'subscription_capabilities_modified',
            'App\Modules\Tenant\Models\Subscription',
            $subscription->id,
            [],
            [
                'limit_overrides' => $limitOverrides, 
                'module_overrides' => $moduleOverrides,
                'message' => "SuperAdmin modified capability overrides for Subscription ID {$subscription->id}"
            ]
        );
    }

    public static function tenantPlanChanged($user, $subscription, $oldPlanId, $newPlanId, $newPlanName)
    {
        self::log(
            $subscription->business_id,
            $user,
            'tenant_plan_changed',
            'App\Modules\Tenant\Models\Subscription',
            $subscription->id,
            ['plan_id' => $oldPlanId],
            ['plan_id' => $newPlanId, 'message' => "Tenant self-service plan change to {$newPlanName}"]
        );
    }
}
