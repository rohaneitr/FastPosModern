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
    /**
     * Receive a Purchase Order and update Weighted Average Cost (WAC)
     */
    public function receive(\Illuminate\Http\Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'supplier_id' => ['required', \Illuminate\Validation\Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'location_id' => ['required', \Illuminate\Validation\Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'reference_no' => 'required|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['required', \Illuminate\Validation\Rule::exists('products', 'id')->where('business_id', $businessId)],
            'lines.*.variation_id' => 'nullable|integer',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $subtotal = 0;
        foreach ($validated['lines'] as $line) {
            $subtotal += ($line['quantity'] * $line['unit_cost']);
        }

        try {
            return DB::transaction(function () use ($validated, $businessId, $request, $subtotal) {

                $transactionId = DB::table('transactions')->insertGetId([
                    'business_id' => $businessId,
                    'location_id' => $validated['location_id'],
                    'contact_id' => $validated['supplier_id'],
                    'created_by' => $request->user()->id,
                    'type' => 'purchase',
                    'status' => 'received',
                    'invoice_no' => $validated['reference_no'],
                    'transaction_date' => \Carbon\Carbon::now(),
                    'total_before_tax' => $subtotal,
                    'final_total' => $subtotal,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now(),
                ]);

                // We MUST sort product_ids to prevent deadlocks when locking multiple rows
                $sortedLines = $validated['lines'];
                usort($sortedLines, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

                foreach ($sortedLines as $line) {
                    $productId = $line['product_id'];
                    $quantity = (float) $line['quantity'];
                    $newUnitCost = (float) $line['unit_cost'];

                    // 1. Pessimistic Lock on Inventory Row
                    $stock = DB::table('product_stocks')
                        ->where('product_id', $productId)
                        ->where('location_id', $validated['location_id'])
                        ->lockForUpdate()
                        ->first();

                    $oldQty = $stock ? (float) $stock->qty_available : 0.0;

                    // 2. Fetch Old MAC (from variations or products)
                    $variationQuery = DB::table('variations')->where('product_id', $productId);
                    if (!empty($line['variation_id'])) {
                        $variationQuery->where('id', $line['variation_id']);
                    }
                    $variation = $variationQuery->first();
                    
                    // Fallback to product if variation doesn't have it
                    $product = DB::table('products')->where('id', $productId)->first();
                    $oldMac = $variation ? (float) ($variation->default_purchase_price ?? 0) : (float) ($product->purchase_price ?? 0);

                    // 3. WAC Math Formula
                    $newTotalQty = $oldQty + $quantity;
                    $newMac = 0.0;

                    if ($newTotalQty > 0) {
                        $oldValue = $oldQty * $oldMac;
                        $newValue = $quantity * $newUnitCost;
                        $newMac = ($oldValue + $newValue) / $newTotalQty;
                    } else {
                        $newMac = $newUnitCost;
                    }

                    // 4. Update Inventory Quantity
                    if ($stock) {
                        DB::table('product_stocks')
                            ->where('id', $stock->id)
                            ->update(['qty_available' => $newTotalQty, 'updated_at' => \Carbon\Carbon::now()]);
                    } else {
                        DB::table('product_stocks')->insert([
                            'business_id' => $businessId,
                            'location_id' => $validated['location_id'],
                            'product_id' => $productId,
                            'qty_available' => $newTotalQty,
                            'created_at' => \Carbon\Carbon::now(),
                            'updated_at' => \Carbon\Carbon::now(),
                        ]);
                    }

                    // 5. Update Cost Baseline (The MAC)
                    if ($variation) {
                        DB::table('variations')
                            ->where('id', $variation->id)
                            ->update(['default_purchase_price' => clone \Brick\Math\BigDecimal::of($newMac)->toScale(4)->__toString()]);
                    }
                    
                    DB::table('products')
                        ->where('id', $productId)
                        ->update(['purchase_price' => clone \Brick\Math\BigDecimal::of($newMac)->toScale(4)->__toString()]);

                    // 6. Double-Entry Audit Trail
                    DB::table('stock_ledgers')->insert([
                        'business_id' => $businessId,
                        'product_id' => $productId,
                        'transaction_type' => 'purchase',
                        'quantity' => $quantity,
                        'created_at' => \Carbon\Carbon::now(),
                        'updated_at' => \Carbon\Carbon::now(),
                    ]);

                    // Insert into transaction_lines
                    DB::table('transaction_lines')->insert([
                        'transaction_id' => $transactionId,
                        'product_id' => $productId,
                        'variation_id' => $line['variation_id'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $newUnitCost,
                        'created_at' => \Carbon\Carbon::now(),
                        'updated_at' => \Carbon\Carbon::now(),
                    ]);
                }

                return response()->json([
                    'message' => 'Purchase received successfully. WAC updated.',
                    'transaction_id' => $transactionId
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to process purchase.', 'error' => $e->getMessage()], 500);
        }
    }
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
