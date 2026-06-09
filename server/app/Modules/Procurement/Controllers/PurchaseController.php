<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\Purchase;
use App\Modules\Procurement\Models\PurchaseLine;
use App\Modules\Procurement\Requests\StorePurchaseRequest;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function index()
    {
        try {
            $purchases = Purchase::with(['contact', 'lines.product'])->latest()->get();
            return response()->json(['data' => $purchases]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch purchases', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StorePurchaseRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $businessId = auth()->user()->business_id;
                
                $referenceNo = $data['reference_no'] ?? 'PO-' . strtoupper(Str::random(6));

                $purchase = Purchase::create([
                    'business_id' => $businessId,
                    'contact_id' => $data['contact_id'],
                    'reference_no' => $referenceNo,
                    'purchase_date' => $data['purchase_date'],
                    'status' => $data['status'],
                    'note' => $data['note'] ?? null,
                    'grand_total' => 0,
                ]);

                $subtotal = \App\Modules\Sales\Services\FinancialCalculator::of(0);

                foreach ($data['lines'] as $lineData) {
                    $lineSub = \App\Modules\Sales\Services\FinancialCalculator::calculateLineTotal($lineData['purchase_price'], $lineData['quantity']);
                    $subtotal = $subtotal->plus($lineSub);

                    $purchaseLine = $purchase->lines()->create([
                        'product_id' => $lineData['product_id'],
                        'quantity' => $lineData['quantity'],
                        'purchase_price' => $lineData['purchase_price'],
                        'item_tax' => '0.0000',
                        'sub_total' => (string) $lineSub,
                    ]);

                    if ($purchase->status === 'received') {
                        StockLedger::create([
                            'business_id' => $purchase->business_id,
                            'product_id' => $lineData['product_id'],
                            'transaction_type' => 'purchase',
                            'transaction_id' => $purchase->id,
                            'quantity' => $lineData['quantity'],
                        ]);

                        // True-Up Negative Stock
                        $reconcileAction = app(\App\Modules\Inventory\Actions\ReconcileNegativeLayersAction::class);
                        $remainingQty = $reconcileAction->execute($purchase->business_id, $lineData['product_id'], $lineData['quantity'], $lineData['purchase_price'], $purchase->id, request()->user()->id ?? null);

                        // Strict FIFO Cost Layer Integration
                        if (\Brick\Math\BigDecimal::of($remainingQty)->isGreaterThan(0)) {
                            \App\Modules\Inventory\Models\InventoryLayer::create([
                                'business_id' => $purchase->business_id,
                                'product_id' => $lineData['product_id'],
                                'purchase_line_id' => $purchaseLine->id,
                                'original_qty' => $remainingQty,
                                'remaining_qty' => $remainingQty,
                                'unit_cost' => $lineData['purchase_price'],
                            ]);
                        }
                    }
                }

                $taxRate = $data['tax_rate'] ?? 0;
                $discValue = \App\Modules\Sales\Services\FinancialCalculator::of(0);
                if (!empty($data['discount_type']) && !empty($data['discount_amount'])) {
                    if ($data['discount_type'] === 'percentage') {
                        $discValue = \App\Modules\Sales\Services\FinancialCalculator::calculatePercentageDiscount($subtotal, $data['discount_amount']);
                    } else {
                        $discValue = \App\Modules\Sales\Services\FinancialCalculator::of($data['discount_amount']);
                        if ($discValue->isGreaterThan($subtotal)) {
                            $discValue = clone $subtotal;
                        }
                    }
                } elseif (!empty($data['discount_amount'])) {
                     $discValue = \App\Modules\Sales\Services\FinancialCalculator::of($data['discount_amount']);
                     if ($discValue->isGreaterThan($subtotal)) {
                         $discValue = clone $subtotal;
                     }
                }

                $afterDisc  = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($subtotal, $discValue);
                $taxAmount  = \App\Modules\Sales\Services\FinancialCalculator::calculateTax($afterDisc, $taxRate);
                $shipping   = \App\Modules\Sales\Services\FinancialCalculator::of($data['shipping_charges'] ?? 0);
                $grandTotal = $afterDisc->plus($taxAmount)->plus($shipping);
                $amtPaid    = \App\Modules\Sales\Services\FinancialCalculator::of($data['amount_paid'] ?? 0);
                $payStatus  = $amtPaid->isGreaterThanOrEqualTo($grandTotal) ? 'paid' : ($amtPaid->isGreaterThan(0) ? 'partial' : 'due');

                $purchase->update([
                    'total_before_tax' => (string) $subtotal,
                    'tax_amount' => (string) $taxAmount,
                    'discount_amount' => (string) $discValue,
                    'discount_type' => $data['discount_type'] ?? null,
                    'shipping_charges' => (string) $shipping,
                    'grand_total' => (string) $grandTotal,
                    'amount_due' => (string) \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($grandTotal, $amtPaid),
                    'payment_status' => $payStatus,
                ]);

                DB::table('supplier_ledgers')->insert([
                    'business_id' => $businessId,
                    'contact_id' => $data['contact_id'],
                    'purchase_id' => $purchase->id,
                    'amount' => (string) $grandTotal,
                    'type' => 'credit',
                    'description' => 'Purchase ' . $referenceNo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 4. Double-Entry Ledger Hook
                $inventoryAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);
                $cashAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH);
                $apAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::AP);

                $debits = [];
                $credits = [];

                // Debit: Inventory Asset (Grand Total)
                if ($grandTotal->isGreaterThan(0)) {
                    $debits[] = ['chart_of_account_id' => $inventoryAccountId, 'amount' => (string) $grandTotal];
                }

                // Credit: Cash (Amount Paid)
                if ($amtPaid->isGreaterThan(0)) {
                    $appliedPaid = $amtPaid->isGreaterThan($grandTotal) ? clone $grandTotal : clone $amtPaid;
                    $credits[] = ['chart_of_account_id' => $cashAccountId, 'amount' => (string) $appliedPaid];
                }

                // Credit: Accounts Payable (Amount Due)
                $amtDue = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($grandTotal, $amtPaid);
                if ($amtDue->isGreaterThan(0)) {
                    $credits[] = ['chart_of_account_id' => $apAccountId, 'amount' => (string) $amtDue];
                }

                $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);
                $journalEntry = $ledger->recordEntry(
                    $businessId,
                    $referenceNo,
                    $data['purchase_date'],
                    'Purchase ' . $referenceNo,
                    $debits,
                    $credits,
                    $purchase->id,
                    'purchase',
                    auth()->id()
                );

                // Forensic Audit Snapshot
                $auditService = app(\App\Modules\Security\Services\ForensicAuditService::class);
                $auditService->snapshot(
                    'journal_entries',
                    $journalEntry->id,
                    'created',
                    'purchase_ledger_created',
                    null,
                    $journalEntry->toArray(),
                    request()->path()
                );

                return response()->json([
                    'message' => 'Purchase created successfully',
                    'data' => $purchase->load(['contact', 'lines.product'])
                ], 201);
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to create purchase', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Purchase $purchase)
    {
        try {
            return response()->json(['data' => $purchase->load(['contact', 'lines.product'])]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch purchase', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(StorePurchaseRequest $request, Purchase $purchase)
    {
        try {
            return DB::transaction(function () use ($request, $purchase) {
                $data = $request->validated();
                $oldStatus = $purchase->status;
                $newStatus = $data['status'];
                
                // Revert old stock if previously received
                if ($oldStatus === 'received') {
                    StockLedger::where('transaction_type', 'purchase')
                        ->where('transaction_id', $purchase->id)
                        ->delete();
                }

                // Clear old lines
                $purchase->lines()->delete();

                // Update purchase info
                $purchase->update([
                    'contact_id' => $data['contact_id'],
                    'reference_no' => $data['reference_no'] ?? $purchase->reference_no,
                    'purchase_date' => $data['purchase_date'],
                    'status' => $newStatus,
                    'note' => $data['note'] ?? $purchase->note,
                    'grand_total' => 0, // Recalculate
                ]);

                $subtotal = \App\Modules\Sales\Services\FinancialCalculator::of(0);
                
                // Create new lines and apply stock if new status is 'received'
                foreach ($data['lines'] as $lineData) {
                    $lineSub = \App\Modules\Sales\Services\FinancialCalculator::calculateLineTotal($lineData['purchase_price'], $lineData['quantity']);
                    $subtotal = $subtotal->plus($lineSub);

                    $purchaseLine = $purchase->lines()->create([
                        'product_id' => $lineData['product_id'],
                        'quantity' => $lineData['quantity'],
                        'purchase_price' => $lineData['purchase_price'],
                        'item_tax' => '0.0000',
                        'sub_total' => (string) $lineSub,
                    ]);

                    if ($newStatus === 'received') {
                        StockLedger::create([
                            'business_id' => $purchase->business_id,
                            'product_id' => $lineData['product_id'],
                            'transaction_type' => 'purchase',
                            'transaction_id' => $purchase->id,
                            'quantity' => $lineData['quantity'],
                        ]);

                        // True-Up Negative Stock
                        $reconcileAction = app(\App\Modules\Inventory\Actions\ReconcileNegativeLayersAction::class);
                        $remainingQty = $reconcileAction->execute($purchase->business_id, $lineData['product_id'], $lineData['quantity'], $lineData['purchase_price'], $purchase->id);

                        // Strict FIFO Cost Layer Integration
                        if (\Brick\Math\BigDecimal::of($remainingQty)->isGreaterThan(0)) {
                            \App\Modules\Inventory\Models\InventoryLayer::create([
                                'business_id' => $purchase->business_id,
                                'product_id' => $lineData['product_id'],
                                'purchase_line_id' => $purchaseLine->id,
                                'original_qty' => $remainingQty,
                                'remaining_qty' => $remainingQty,
                                'unit_cost' => $lineData['purchase_price'],
                            ]);
                        }
                    }
                }

                $taxRate = $data['tax_rate'] ?? 0;
                $discValue = \App\Modules\Sales\Services\FinancialCalculator::of(0);
                if (!empty($data['discount_type']) && !empty($data['discount_amount'])) {
                    if ($data['discount_type'] === 'percentage') {
                        $discValue = \App\Modules\Sales\Services\FinancialCalculator::calculatePercentageDiscount($subtotal, $data['discount_amount']);
                    } else {
                        $discValue = \App\Modules\Sales\Services\FinancialCalculator::of($data['discount_amount']);
                        if ($discValue->isGreaterThan($subtotal)) {
                            $discValue = clone $subtotal;
                        }
                    }
                } elseif (!empty($data['discount_amount'])) {
                     $discValue = \App\Modules\Sales\Services\FinancialCalculator::of($data['discount_amount']);
                     if ($discValue->isGreaterThan($subtotal)) {
                         $discValue = clone $subtotal;
                     }
                }

                $afterDisc  = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($subtotal, $discValue);
                $taxAmount  = \App\Modules\Sales\Services\FinancialCalculator::calculateTax($afterDisc, $taxRate);
                $shipping   = \App\Modules\Sales\Services\FinancialCalculator::of($data['shipping_charges'] ?? 0);
                $grandTotal = $afterDisc->plus($taxAmount)->plus($shipping);
                $amtPaid    = \App\Modules\Sales\Services\FinancialCalculator::of($data['amount_paid'] ?? 0);
                $payStatus  = $amtPaid->isGreaterThanOrEqualTo($grandTotal) ? 'paid' : ($amtPaid->isGreaterThan(0) ? 'partial' : 'due');

                $purchase->update([
                    'total_before_tax' => (string) $subtotal,
                    'tax_amount' => (string) $taxAmount,
                    'discount_amount' => (string) $discValue,
                    'discount_type' => $data['discount_type'] ?? null,
                    'shipping_charges' => (string) $shipping,
                    'grand_total' => (string) $grandTotal,
                    'amount_due' => (string) \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($grandTotal, $amtPaid),
                    'payment_status' => $payStatus,
                ]);

                // Update ledger
                DB::table('supplier_ledgers')
                    ->where('purchase_id', $purchase->id)
                    ->update([
                        'contact_id' => $data['contact_id'],
                        'amount' => (string) $grandTotal,
                        'updated_at' => now(),
                    ]);

                // 4. Double-Entry Ledger Hook (Recreate for update)
                $businessId = auth()->user()->business_id;
                
                // Fetch the existing journal entry to delete it and take snapshot
                $oldEntry = \App\Models\JournalEntry::where('reference_type', 'purchase')
                                ->where('reference_id', $purchase->id)
                                ->first();
                $oldEntryArray = $oldEntry ? $oldEntry->toArray() : null;

                if ($oldEntry) {
                    \App\Models\JournalLine::where('journal_entry_id', $oldEntry->id)->delete();
                    $oldEntry->delete();
                }

                $inventoryAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);
                $cashAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH);
                $apAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::AP);

                $debits = [];
                $credits = [];

                if ($grandTotal->isGreaterThan(0)) {
                    $debits[] = ['chart_of_account_id' => $inventoryAccountId, 'amount' => (string) $grandTotal];
                }

                if ($amtPaid->isGreaterThan(0)) {
                    $appliedPaid = $amtPaid->isGreaterThan($grandTotal) ? clone $grandTotal : clone $amtPaid;
                    $credits[] = ['chart_of_account_id' => $cashAccountId, 'amount' => (string) $appliedPaid];
                }

                $amtDue = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($grandTotal, $amtPaid);
                if ($amtDue->isGreaterThan(0)) {
                    $credits[] = ['chart_of_account_id' => $apAccountId, 'amount' => (string) $amtDue];
                }

                $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);
                $journalEntry = $ledger->recordEntry(
                    $businessId,
                    $purchase->reference_no,
                    $data['purchase_date'],
                    'Purchase Update ' . $purchase->reference_no,
                    $debits,
                    $credits,
                    $purchase->id,
                    'purchase',
                    auth()->id()
                );

                // Forensic Audit Snapshot (Update)
                $auditService = app(\App\Modules\Security\Services\ForensicAuditService::class);
                $auditService->snapshot(
                    'journal_entries',
                    $journalEntry->id,
                    'updated',
                    'purchase_ledger_updated',
                    $oldEntryArray,
                    $journalEntry->toArray(),
                    request()->path()
                );

                return response()->json([
                    'message' => 'Purchase updated successfully',
                    'data' => $purchase->fresh(['contact', 'lines.product'])
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to update purchase', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Purchase $purchase)
    {
        try {
            return DB::transaction(function () use ($purchase) {
                if ($purchase->status === 'received') {
                    StockLedger::where('transaction_type', 'purchase')
                        ->where('transaction_id', $purchase->id)
                        ->delete();
                }

                $purchase->delete();

                return response()->json(['message' => 'Purchase deleted successfully']);
            });
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['message' => 'Cannot delete purchase because it is linked to other records.'], 409);
            }
            return response()->json(['message' => 'Failed to delete purchase', 'error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to delete purchase', 'error' => $e->getMessage()], 500);
        }
    }
}
