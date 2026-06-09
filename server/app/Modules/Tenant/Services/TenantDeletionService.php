<?php

namespace App\Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TenantDeletionService
 *
 * Deep cascade hard delete of all data belonging to a single business/tenant.
 * Tables are deleted in child-to-parent (FK constraint) order so no integrity
 * violations occur, even on databases that enforce FK constraints.
 *
 * All operations run inside a single DB::transaction so the dataset is NEVER
 * left in a half-deleted state if a step fails.
 */
class TenantDeletionService
{
    /**
     * Permanently wipe a tenant and every piece of data it owns.
     *
     * @param  int  $businessId
     * @return array{deleted: true, business_id: int, summary: array<string, int>}
     *
     * @throws \Throwable  re-thrown after rollback so the caller can return a 500
     */
    public function wipeTenant(int $businessId): array
    {
        $summary = [];

        DB::transaction(function () use ($businessId, &$summary) {
            // ─── 1. Device / License layer (no dependencies below) ───────────
            $summary['device_activations'] = \Illuminate\Support\Facades\Schema::hasTable('device_activations') 
                ? DB::table('device_activations')->where('business_id', $businessId)->delete() 
                : 0;

            $summary['licenses'] = \Illuminate\Support\Facades\Schema::hasTable('licenses')
                ? DB::table('licenses')->where('tenant_id', $businessId)->delete()
                : 0;

            // ─── 2. Payments ──────────────────────────────────────────────────
            $summary['subscription_payments'] = \Illuminate\Support\Facades\Schema::hasTable('subscription_payments')
                ? DB::table('subscription_payments')->where('business_id', $businessId)->delete()
                : 0;

            $summary['payments'] = \Illuminate\Support\Facades\Schema::hasTable('payments')
                ? DB::table('payments')->where('business_id', $businessId)->delete()
                : 0;

            // ─── 3. Subscriptions ─────────────────────────────────────────────
            $summary['subscriptions'] = \Illuminate\Support\Facades\Schema::hasTable('subscriptions')
                ? DB::table('subscriptions')->where('business_id', $businessId)->delete()
                : 0;

            // ─── 4. Sales / Transactions (child rows first) ───────────────────
            if (\Illuminate\Support\Facades\Schema::hasTable('transactions')) {
                $txIds = DB::table('transactions')->where('business_id', $businessId)->pluck('id')->all();

                if (!empty($txIds)) {
                    $summary['transaction_payments'] = \Illuminate\Support\Facades\Schema::hasTable('transaction_payments')
                        ? DB::table('transaction_payments')->whereIn('transaction_id', $txIds)->delete()
                        : 0;

                    $summary['transaction_lines'] = \Illuminate\Support\Facades\Schema::hasTable('transaction_lines')
                        ? DB::table('transaction_lines')->whereIn('transaction_id', $txIds)->delete()
                        : 0;
                } else {
                    $summary['transaction_payments'] = 0;
                    $summary['transaction_lines'] = 0;
                }

                $summary['transactions'] = DB::table('transactions')->where('business_id', $businessId)->delete();
            } else {
                $summary['transaction_payments'] = 0;
                $summary['transaction_lines'] = 0;
                $summary['transactions'] = 0;
            }

            // ─── 5. Inventory ─────────────────────────────────────────────────
            if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
                $productIds = DB::table('products')->where('business_id', $businessId)->pluck('id')->all();

                if (!empty($productIds)) {
                    $summary['product_serials'] = \Illuminate\Support\Facades\Schema::hasTable('product_serials') ? DB::table('product_serials')->whereIn('product_id', $productIds)->delete() : 0;
                    $summary['product_stocks'] = \Illuminate\Support\Facades\Schema::hasTable('product_stocks') ? DB::table('product_stocks')->whereIn('product_id', $productIds)->delete() : 0;
                    $summary['variations'] = \Illuminate\Support\Facades\Schema::hasTable('variations') ? DB::table('variations')->whereIn('product_id', $productIds)->delete() : 0;
                    $summary['barcodes'] = \Illuminate\Support\Facades\Schema::hasTable('barcodes') ? DB::table('barcodes')->whereIn('product_id', $productIds)->delete() : 0;
                } else {
                    $summary['product_serials'] = 0;
                    $summary['product_stocks'] = 0;
                    $summary['variations'] = 0;
                    $summary['barcodes'] = 0;
                }

                $summary['products'] = DB::table('products')->where('business_id', $businessId)->delete();
            } else {
                $summary['product_serials'] = 0;
                $summary['product_stocks'] = 0;
                $summary['variations'] = 0;
                $summary['barcodes'] = 0;
                $summary['products'] = 0;
            }

            $summary['stock_adjustments'] = \Illuminate\Support\Facades\Schema::hasTable('stock_adjustments')
                ? DB::table('stock_adjustments')->where('business_id', $businessId)->delete()
                : 0;

            // ─── 6. Catalog (shared categories/brands/units are global; only delete business-scoped) ─
            $summary['categories'] = \Illuminate\Support\Facades\Schema::hasTable('categories') ? DB::table('categories')->where('business_id', $businessId)->delete() : 0;
            $summary['locations'] = \Illuminate\Support\Facades\Schema::hasTable('locations') ? DB::table('locations')->where('business_id', $businessId)->delete() : 0;
            $summary['invoice_layouts'] = \Illuminate\Support\Facades\Schema::hasTable('invoice_layouts') ? DB::table('invoice_layouts')->where('business_id', $businessId)->delete() : 0;

            // ─── 7. CRM ───────────────────────────────────────────────────────
            $summary['contacts'] = \Illuminate\Support\Facades\Schema::hasTable('contacts') ? DB::table('contacts')->where('business_id', $businessId)->delete() : 0;

            // ─── 8. Accounting ────────────────────────────────────────────────
            $summary['expenses'] = \Illuminate\Support\Facades\Schema::hasTable('expenses') ? DB::table('expenses')->where('business_id', $businessId)->delete() : 0;
            $summary['expense_categories'] = \Illuminate\Support\Facades\Schema::hasTable('expense_categories') ? DB::table('expense_categories')->where('business_id', $businessId)->delete() : 0;

            // ─── 9. HR ────────────────────────────────────────────────────────
            if (\Illuminate\Support\Facades\Schema::hasTable('employees')) {
                $employeeIds = DB::table('employees')->where('business_id', $businessId)->pluck('id')->all();

                if (!empty($employeeIds)) {
                    $summary['payrolls'] = \Illuminate\Support\Facades\Schema::hasTable('payrolls') ? DB::table('payrolls')->whereIn('employee_id', $employeeIds)->delete() : 0;
                    $summary['attendances'] = \Illuminate\Support\Facades\Schema::hasTable('attendances') ? DB::table('attendances')->whereIn('employee_id', $employeeIds)->delete() : 0;
                } else {
                    $summary['payrolls'] = 0;
                    $summary['attendances'] = 0;
                }
                
                $summary['employees'] = DB::table('employees')->where('business_id', $businessId)->delete();
            } else {
                $summary['payrolls'] = 0;
                $summary['attendances'] = 0;
                $summary['employees'] = 0;
            }

            // ─── 10. Audit / activity trails ──────────────────────────────────
            $summary['audit_logs'] = \Illuminate\Support\Facades\Schema::hasTable('audit_logs') ? DB::table('audit_logs')->where('business_id', $businessId)->delete() : 0;
            $summary['user_activities'] = \Illuminate\Support\Facades\Schema::hasTable('user_activities') ? DB::table('user_activities')->where('business_id', $businessId)->delete() : 0;
            $summary['tenant_requests'] = \Illuminate\Support\Facades\Schema::hasTable('tenant_requests') ? DB::table('tenant_requests')->where('business_id', $businessId)->delete() : 0;

            // ─── 11. Users who belong ONLY to this business (exclude SuperAdmins) ─
            if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                $userIds = DB::table('users')
                    ->where('business_id', $businessId)
                    ->whereNotExists(function ($q) {
                        if (\Illuminate\Support\Facades\Schema::hasTable('model_has_roles')) {
                            $q->select(DB::raw(1))
                              ->from('model_has_roles')
                              ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                              ->whereColumn('model_has_roles.model_id', 'users.id')
                              ->where('model_has_roles.model_type', \App\Models\User::class)
                              ->where('roles.name', 'SuperAdmin');
                        } else {
                            $q->select(DB::raw(0))->whereRaw('1=0');
                        }
                    })
                    ->pluck('id')
                    ->all();

                if (!empty($userIds)) {
                    if (\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
                        DB::table('personal_access_tokens')
                            ->where('tokenable_type', \App\Models\User::class)
                            ->whereIn('tokenable_id', $userIds)
                            ->delete();
                    }

                    if (\Illuminate\Support\Facades\Schema::hasTable('model_has_roles')) {
                        DB::table('model_has_roles')
                            ->whereIn('model_id', $userIds)
                            ->where('model_type', \App\Models\User::class)
                            ->delete();
                    }

                    if (\Illuminate\Support\Facades\Schema::hasTable('model_has_permissions')) {
                        DB::table('model_has_permissions')
                            ->whereIn('model_id', $userIds)
                            ->where('model_type', \App\Models\User::class)
                            ->delete();
                    }

                    if (\Illuminate\Support\Facades\Schema::hasTable('user_activities')) {
                        DB::table('user_activities')
                            ->whereIn('user_id', $userIds)
                            ->delete();
                    }

                    $summary['users'] = DB::table('users')->whereIn('id', $userIds)->delete();
                } else {
                    $summary['users'] = 0;
                }
            } else {
                $summary['users'] = 0;
            }

            // ─── 12. The business itself (hard delete, bypasses SoftDeletes) ──
            $summary['business'] = \Illuminate\Support\Facades\Schema::hasTable('businesses') ? DB::table('businesses')->where('id', $businessId)->delete() : 0;
        });

        Log::info('[TenantDeletion] Wiped tenant', [
            'business_id' => $businessId,
            'summary'     => $summary,
        ]);

        return [
            'deleted'     => true,
            'business_id' => $businessId,
            'summary'     => $summary,
        ];
    }
}
