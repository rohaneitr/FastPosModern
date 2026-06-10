<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class SubscriptionBillingService
{
    /**
     * Calculates the pro-rated cost for upgrading to new modules mid-cycle,
     * and generates a Pending Invoice.
     *
     * @param int $businessId The Tenant
     * @param array $requestedModules Array of module slugs (e.g., ['manufacturing', 'clinical'])
     * @return array The Invoice details
     */
    public function generateUpgradeInvoice(int $businessId, array $requestedModules): array
    {
        $business = DB::table('businesses')->where('id', $businessId)->first();
        if (!$business) {
            throw new Exception("Business not found.");
        }

        $activeModules = json_decode($business->active_modules ?? '[]', true);
        
        // Ensure they aren't paying for what they already own
        $newModulesToBill = array_diff($requestedModules, $activeModules);

        if (empty($newModulesToBill)) {
            throw new Exception("Tenant already possesses all requested modules. No upgrade needed.");
        }

        // Fetch pricing for the new modules
        $modulesPricing = DB::table('subscription_modules')
            ->whereIn('slug', $newModulesToBill)
            ->get();

        if ($modulesPricing->count() !== count($newModulesToBill)) {
            throw new Exception("One or more requested modules are invalid or unavailable.");
        }

        $billingCycleEnd = $business->billing_due_date ? Carbon::parse($business->billing_due_date) : Carbon::now()->addMonth();
        $today = Carbon::now();
        
        if ($today->isAfter($billingCycleEnd)) {
            throw new Exception("Account is past due. Cannot process pro-rata upgrade. Please renew full cycle first.");
        }

        $daysRemainingInCycle = $today->diffInDays($billingCycleEnd);
        $totalDaysInMonth = $today->daysInMonth;

        $totalProratedCost = '0.00';

        foreach ($modulesPricing as $module) {
            // Daily Rate = Monthly Price / Days in current month
            // We use bcmath to prevent precision loss.
            $monthlyPrice = (string)$module->price_per_month;
            
            $dailyRate = bcdiv($monthlyPrice, (string)$totalDaysInMonth, 4);
            $proratedCostForModule = bcmul($dailyRate, (string)$daysRemainingInCycle, 2);
            
            $totalProratedCost = bcadd($totalProratedCost, $proratedCostForModule, 2);
        }

        $invoiceNumber = "INV-UPG-" . strtoupper(uniqid());

        $invoiceId = DB::table('tenant_invoices')->insertGetId([
            'business_id' => $businessId,
            'invoice_number' => $invoiceNumber,
            'type' => 'upgrade_prorated',
            'requested_modules' => json_encode($newModulesToBill),
            'total_amount' => $totalProratedCost,
            'status' => 'Pending',
            'billing_cycle_start' => $today->toDateString(),
            'billing_cycle_end' => $billingCycleEnd->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'amount' => $totalProratedCost,
            'modules_billed' => $newModulesToBill,
            'days_prorated' => $daysRemainingInCycle
        ];
    }
}
