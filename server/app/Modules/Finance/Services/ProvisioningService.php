<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User; // Assuming User model manages Spatie Roles/Permissions
use Spatie\Permission\Models\Permission;
use Exception;

class ProvisioningService
{
    /**
     * Unlocks features for a tenant upon successful payment.
     * Must be called within a DB::transaction by the Payment Listener.
     */
    public function provision(int $invoiceId): void
    {
        $invoice = DB::table('tenant_invoices')->where('id', $invoiceId)->lockForUpdate()->first();

        if (!$invoice || $invoice->status !== 'Paid') {
            throw new Exception("Invoice is invalid or not marked as Paid. Provisioning aborted.");
        }

        $businessId = $invoice->business_id;
        $business = DB::table('businesses')->where('id', $businessId)->first();
        
        $currentModules = json_decode($business->active_modules ?? '[]', true);
        $purchasedModules = json_decode($invoice->requested_modules ?? '[]', true);

        // 1. Merge and Deduplicate Modules
        $newActiveModules = array_unique(array_merge($currentModules, $purchasedModules));

        // 2. Commit Active Modules JSON
        DB::table('businesses')->where('id', $businessId)->update([
            'active_modules' => json_encode(array_values($newActiveModules)),
            'subscription_status' => 'Active',
            // Update due date if this was a renewal (skip if just prorated upgrade)
            'billing_due_date' => $invoice->type === 'renewal' ? \Carbon\Carbon::parse($invoice->billing_cycle_end)->toDateString() : $business->billing_due_date,
            'updated_at' => now(),
        ]);

        // 3. Spatie Permission Sync (The Hard Enforcement)
        // Find the Business Owner to grant them the new root permissions.
        // Once the owner has the module.* permission, they can delegate it to staff via the TenantAdmin UI.
        $owner = User::where('business_id', $businessId)->role('business_owner')->first();
        
        if ($owner) {
            foreach ($purchasedModules as $module) {
                $permissionName = "module.{$module}";
                
                // Ensure permission exists in DB
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
                
                // Assign to owner
                $owner->givePermissionTo($permissionName);
            }
        }
    }
}
