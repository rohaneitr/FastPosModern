<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Actions\CalculateSaleTotalsAction;
use App\Modules\Sales\DataTransferObjects\SaleCheckoutDTO;
use App\Modules\Sales\DataTransferObjects\SaleCheckoutResult;
use App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction;
use App\Modules\Finance\Services\TenantAccountResolver;
use App\Modules\Finance\Services\DoubleEntryEngine;
use App\Modules\Security\Services\ForensicAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * ProcessSaleService
 *
 * Extracted from TransactionController::checkout() (was 680 lines).
 * Single Responsibility: process a validated, priced sale to completion.
 *
 * This service ONLY handles business logic. It does NOT:
 * - Validate HTTP requests (Controller responsibility)
 * - Build HTTP responses (Controller responsibility)
 * - Handle authentication (Middleware responsibility)
 *
 * All external dependencies are injected via constructor (testable).
 *
 * @author  Antigravity AI Agent — Phase 3
 * @version 2026-06-12
 */
class ProcessSaleService
{
    public function __construct(
        private readonly CalculateSaleTotalsAction       $calculateTotals,
        private readonly ConsumeBatchFIFOInventoryAction $consumeFifo,
        private readonly DoubleEntryEngine               $doubleEntry,
    ) {}

    /**
     * Execute the full sale checkout pipeline.
     *
     * @throws \Illuminate\Validation\ValidationException  — Stock insufficient
     * @throws \Exception                                  — Business rule violation
     */
    public function execute(SaleCheckoutDTO $dto): SaleCheckoutResult
    {
        // ── 0. Idempotency Guard ─────────────────────────────────────────────
        if ($dto->isPosting && $dto->idempotencyKey) {
            $existing = DB::table('transactions')
                ->where('idempotency_key', $dto->idempotencyKey)
                ->where('business_id', $dto->businessId)
                ->first();

            if ($existing) {
                return new SaleCheckoutResult(
                    transactionId: $existing->id,
                    invoiceNo:     $existing->invoice_no,
                    subtotal:      '0',
                    discount:      '0',
                    tax:           '0',
                    finalTotal:    (string) $existing->final_total,
                );
            }
        }

        // ── 1. Calculate Totals (Zero-Trust Pricing) ─────────────────────────
        $totals    = $this->calculateTotals->execute(
            $dto->items,
            $dto->taxRate,
            $dto->discountType,
            $dto->discountAmount,
        );

        $invoiceNo   = ($dto->isPosting ? 'INV-' : 'QT-') . time() . '-' . mt_rand(100, 999);
        $amountPaid  = isset($dto->amountPaid)
            ? FinancialCalculator::of($dto->amountPaid)
            : clone $totals->finalTotal;
        $amountDue   = FinancialCalculator::applyDiscount($totals->finalTotal, $amountPaid);

        // ── 2. Pharmacy Rx Shield ────────────────────────────────────────────
        $productIds = collect($dto->items)->pluck('product_id')->unique()->values()->toArray();
        $rxCount    = DB::table('medicines_meta')
            ->whereIn('product_id', $productIds)
            ->where('is_rx_required', true)
            ->count();

        if ($rxCount > 0) {
            $hasRx = !empty($dto->prescriptionDoctor) || !empty($dto->prescriptionPatient) || !empty($dto->prescriptionFile);
            if (!$hasRx) {
                throw new \Exception('FPM Compliance: One or more medicines require a valid prescription.');
            }
        }

        // ── 3. Credit sale guard ─────────────────────────────────────────────
        if ($dto->isPosting && $amountDue->isGreaterThan(0.01) && empty($dto->contactId)) {
            throw new \Exception('Customer MUST be selected for credit sales / dues.');
        }

        // ── 4. Advance / Store Credit balance checks ─────────────────────────
        if ($dto->isPosting && $dto->paymentMethod === 'store_credit' && $amountPaid->isGreaterThan(0)) {
            $this->validateStoreCredit($dto->contactId, $amountPaid);
        }

        // ── 5. Main DB Transaction ───────────────────────────────────────────
        $transactionId = DB::transaction(function () use ($dto, $totals, $invoiceNo, $amountPaid, $amountDue, $rxCount, $productIds) {

            $paymentStatus = 'paid';
            if (!$dto->isPosting || $amountDue->isGreaterThan(0.01)) {
                $paymentStatus = ($amountPaid->isGreaterThan(0) && $dto->isPosting) ? 'partial' : 'due';
            }

            // 5a. Insert header transaction
            $txId = DB::table('transactions')->insertGetId([
                'business_id'      => $dto->businessId,
                'location_id'      => $dto->locationId,
                'created_by'       => $dto->userId,
                'contact_id'       => $dto->contactId,
                'type'             => 'sell',
                'status'           => $dto->isPosting ? 'final' : 'draft',
                'is_quotation'     => !$dto->isPosting,
                'document_type'    => $dto->documentType,
                'invoice_no'       => $invoiceNo,
                'transaction_date' => $dto->transactionDate ? Carbon::parse($dto->transactionDate) : Carbon::now(),
                'total_before_tax' => (string) $totals->afterDiscount,
                'tax_amount'       => (string) $totals->taxAmount,
                'discount_amount'  => (string) $totals->discountValue,
                'discount_type'    => $dto->discountType,
                'final_total'      => (string) $totals->finalTotal,
                'payment_status'   => $paymentStatus,
                'cash_register_id' => $dto->cashRegisterId,
                'idempotency_key'  => $dto->idempotencyKey,
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ]);

            // 5b. Optional: Prescription record
            $prescriptionId = null;
            if ($rxCount > 0 || !empty($dto->prescriptionDoctor) || !empty($dto->prescriptionPatient)) {
                $prescriptionId = DB::table('prescriptions')->insertGetId([
                    'business_id'    => $dto->businessId,
                    'transaction_id' => $txId,
                    'doctor_name'    => $dto->prescriptionDoctor,
                    'patient_id'     => $dto->prescriptionPatient,
                    'file_path'      => $dto->prescriptionFile,
                    'notes'          => $dto->prescriptionNotes,
                    'created_at'     => Carbon::now(),
                    'updated_at'     => Carbon::now(),
                ]);
            }

            // 5c. Composite / standard product mapping
            $products             = DB::table('products')->whereIn('id', $productIds)->get()->keyBy('id');
            $assemblies           = DB::table('product_assemblies')->whereIn('parent_product_id', $productIds)->get();
            $compositeChildrenMap = [];
            foreach ($assemblies as $asm) {
                $compositeChildrenMap[$asm->parent_product_id][$asm->child_product_id] = $asm->quantity;
            }

            // 5d. Resolve deductible quantities (stock check)
            [$deductibleQty, $pendingSourcingProducts, $isPendingParts] = $this->resolveStockDeductions(
                $dto, $totals->enrichedItems, $products, $compositeChildrenMap
            );

            // 5e. FIFO cost consumption
            $cogsMap      = $dto->isPosting ? $this->consumeFifo->execute($dto->businessId, $deductibleQty) : [];
            $finalCogsMap = $this->buildCompositeCogsMap($totals->enrichedItems, $products, $compositeChildrenMap, $cogsMap);

            if ($isPendingParts) {
                DB::table('transactions')->where('id', $txId)->update(['sourcing_status' => 'pending_parts']);
            }

            // 5f. Sort items to prevent deadlocks, then insert lines
            $sortedItems = $totals->enrichedItems;
            usort($sortedItems, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

            $totalCogs = FinancialCalculator::of(0);

            foreach ($sortedItems as $item) {
                $fractionalRatio  = $item['fractional_ratio'] ?? 1.0;
                $actualQty        = $item['quantity'] * $fractionalRatio;
                $productType      = $products->get($item['product_id'])->type ?? 'standard';
                $lineSourcingStatus = 'ready';

                if ($dto->isPosting && $productType === 'composite' && isset($compositeChildrenMap[$item['product_id']])) {
                    foreach ($compositeChildrenMap[$item['product_id']] as $childId => $qtyPerParent) {
                        if (isset($pendingSourcingProducts[$childId])) {
                            $lineSourcingStatus = 'pending_sourcing';
                            break;
                        }
                    }
                }

                $lineId = DB::table('transaction_lines')->insertGetId([
                    'business_id'               => $dto->businessId,
                    'transaction_id'            => $txId,
                    'product_id'                => $item['product_id'],
                    'variation_id'              => $item['variation_id'] ?? null,
                    'quantity'                  => $actualQty,
                    'unit_price_before_discount'=> $item['price'],
                    'unit_price'                => $item['price'],
                    'unit_price_inc_tax'        => (string) FinancialCalculator::add($item['price'], FinancialCalculator::calculateTax($item['price'], $dto->taxRate)),
                    'item_tax'                  => (string) FinancialCalculator::calculateTax($item['price'], $dto->taxRate),
                    'tax_rate'                  => $dto->taxRate,
                    'tax_amount'                => (string) FinancialCalculator::calculateTax($item['price'] * $actualQty, $dto->taxRate),
                    'warranty_duration'         => $item['warranty_duration'] ?? null,
                    'prescription_id'           => $prescriptionId,
                    'dosage_instructions'       => $item['dosage_instructions'] ?? null,
                    'sourcing_status'           => $lineSourcingStatus,
                    'created_at'               => Carbon::now(),
                    'updated_at'               => Carbon::now(),
                ]);

                // 5g. Inventory deduction + serial tracking
                if ($dto->isPosting) {
                    $lineCogs = $this->deductInventoryForLine(
                        $item, $actualQty, $dto, $lineId,
                        $products, $compositeChildrenMap, $pendingSourcingProducts,
                        $cogsMap, $finalCogsMap
                    );
                    $totalCogs = $totalCogs->plus($lineCogs);
                }
            }

            // 5h. Insert payment record
            if ($dto->isPosting && $amountPaid->isGreaterThan(0)) {
                DB::table('transaction_payments')->insert([
                    'business_id'    => $dto->businessId,
                    'transaction_id' => $txId,
                    'amount'         => (string) $amountPaid,
                    'method'         => $dto->paymentMethod,
                    'paid_on'        => Carbon::now(),
                    'created_by'     => $dto->userId,
                    'created_at'     => Carbon::now(),
                    'updated_at'     => Carbon::now(),
                ]);
            }

            // 5i. Mark source quotation converted
            if ($dto->isPosting && $dto->convertQuotationId) {
                DB::table('transactions')
                    ->where('id', $dto->convertQuotationId)
                    ->where('business_id', $dto->businessId)
                    ->update(['status' => 'converted']);
            }

            // 5j. Double-Entry Ledger
            if ($dto->isPosting) {
                $this->postDoubleEntry($dto->businessId, $dto->userId, $invoiceNo, $txId, $totals, $amountPaid, $amountDue, $totalCogs);
            }

            // 5k. Loyalty Points
            if ($dto->isPosting && $dto->contactId && $totals->finalTotal->isGreaterThan(0)) {
                $this->awardLoyaltyPoints($dto->businessId, $dto->contactId, $txId, $invoiceNo, $totals->finalTotal);
            }

            return $txId;
        }, 5);

        // Bust dashboard KPI cache
        Cache::forget("dashboard_kpis_business_{$dto->businessId}");

        return new SaleCheckoutResult(
            transactionId: $transactionId,
            invoiceNo:     $invoiceNo,
            subtotal:      FinancialCalculator::toFloat($totals->subtotal),
            discount:      FinancialCalculator::toFloat($totals->discountValue),
            tax:           FinancialCalculator::toFloat($totals->taxAmount),
            finalTotal:    FinancialCalculator::toFloat($totals->finalTotal),
        );
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function validateStoreCredit(?int $contactId, mixed $amountPaid): void
    {
        if (!$contactId) {
            throw new \Exception('Customer MUST be selected to use Store Credit.');
        }
        $wallet = DB::table('customer_wallets')
            ->where('contact_id', $contactId)
            ->lockForUpdate()
            ->first();

        if (!$wallet || FinancialCalculator::of($wallet->balance)->isLessThan($amountPaid)) {
            throw new \Exception('Location Overdraft: Insufficient store credit balance.');
        }
        DB::table('customer_wallets')->where('id', $wallet->id)->decrement('balance', (string) $amountPaid);
    }

    private function resolveStockDeductions(SaleCheckoutDTO $dto, array $enrichedItems, $products, array $compositeChildrenMap): array
    {
        $productQuantities = [];

        foreach ($enrichedItems as $item) {
            $fractionalRatio = $item['fractional_ratio'] ?? 1.0;
            $actualQty       = $item['quantity'] * $fractionalRatio;
            $productType     = $products->get($item['product_id'])->type ?? 'standard';

            if ($productType === 'composite' && isset($compositeChildrenMap[$item['product_id']])) {
                foreach ($compositeChildrenMap[$item['product_id']] as $childId => $qtyPerParent) {
                    $productQuantities[$childId] = ($productQuantities[$childId] ?? 0) + ($actualQty * $qtyPerParent);
                }
            } else {
                $productQuantities[$item['product_id']] = ($productQuantities[$item['product_id']] ?? 0) + $actualQty;
            }
        }

        if (!$dto->isPosting) {
            return [$productQuantities, [], false];
        }

        $stockMap = DB::table('product_stocks')
            ->whereIn('product_id', array_keys($productQuantities))
            ->where('location_id', $dto->locationId)
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        $deductible             = [];
        $pendingSourcingProducts = [];
        $isPendingParts         = false;

        foreach ($productQuantities as $prodId => $reqQty) {
            $available = $stockMap->has($prodId) ? (float) $stockMap->get($prodId)->qty_available : 0;

            if ($available >= $reqQty) {
                $deductible[$prodId] = $reqQty;
            } else {
                // Check if from composite — allow pending sourcing
                $isFromComposite = false;
                foreach ($enrichedItems as $item) {
                    $type = $products->get($item['product_id'])->type ?? 'standard';
                    if ($type === 'composite' && isset($compositeChildrenMap[$item['product_id']][$prodId])) {
                        $isFromComposite = true;
                        break;
                    }
                }

                if ($isFromComposite) {
                    $pendingSourcingProducts[$prodId] = true;
                    $isPendingParts = true;
                } else {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'inventory' => ["Strict POS Limit: Insufficient stock for product ID: $prodId"]
                    ]);
                }
            }
        }

        return [$deductible, $pendingSourcingProducts, $isPendingParts];
    }

    private function buildCompositeCogsMap(array $enrichedItems, $products, array $compositeChildrenMap, array $cogsMap): array
    {
        $finalCogsMap = [];
        foreach ($enrichedItems as $item) {
            $productType = $products->get($item['product_id'])->type ?? 'standard';
            $fractionalRatio = $item['fractional_ratio'] ?? 1.0;
            $actualQty       = $item['quantity'] * $fractionalRatio;

            if ($productType === 'composite' && isset($compositeChildrenMap[$item['product_id']])) {
                $compositeCogs = 0;
                foreach ($compositeChildrenMap[$item['product_id']] as $childId => $qtyPerParent) {
                    $compositeCogs += ($cogsMap[$childId] ?? 0) * $qtyPerParent;
                }
                $finalCogsMap[$item['product_id']] = $compositeCogs * $actualQty;
            } else {
                $finalCogsMap[$item['product_id']] = ($cogsMap[$item['product_id']] ?? 0) * $actualQty;
            }
        }
        return $finalCogsMap;
    }

    private function deductInventoryForLine(
        array $item, float $actualQty, SaleCheckoutDTO $dto,
        int $lineId, $products, array $compositeChildrenMap,
        array $pendingSourcingProducts, array $cogsMap, array $finalCogsMap
    ): mixed {
        $productType = $products->get($item['product_id'])->type ?? 'standard';
        $lineCogs    = FinancialCalculator::of(0);

        if ($productType === 'composite' && isset($compositeChildrenMap[$item['product_id']])) {
            foreach ($compositeChildrenMap[$item['product_id']] as $childId => $qtyPerParent) {
                if (isset($pendingSourcingProducts[$childId])) continue;

                $totalChildQty = $actualQty * $qtyPerParent;
                $stock = DB::table('product_stocks')
                    ->where('product_id', $childId)
                    ->where('location_id', $dto->locationId)
                    ->lockForUpdate()->first();

                if (!$stock || $stock->qty_available < $totalChildQty) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'inventory' => ["Insufficient stock for kit component ID: $childId"]
                    ]);
                }

                DB::table('product_stocks')->where('id', $stock->id)
                    ->decrement('qty_available', $totalChildQty);
            }
            $lineCogs = FinancialCalculator::of($finalCogsMap[$item['product_id']] ?? 0);
        } else {
            $stock = DB::table('product_stocks')
                ->where('product_id', $item['product_id'])
                ->where('location_id', $dto->locationId)
                ->lockForUpdate()->first();

            if (!$stock || $stock->qty_available < $actualQty) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inventory' => ['Insufficient stock available for the requested product.']
                ]);
            }

            DB::table('product_stocks')->where('id', $stock->id)
                ->decrement('qty_available', $actualQty);

            $lineCogs = FinancialCalculator::of($finalCogsMap[$item['product_id']] ?? 0);
        }

        // Serial tracking
        $productMeta    = $products->get($item['product_id']);
        $requiresSerial = isset($productMeta->enable_sr_no) && $productMeta->enable_sr_no == 1;

        if ($requiresSerial && empty($item['serial_numbers']) && empty($item['imei_numbers'])) {
            throw new \Exception('Product ID ' . $item['product_id'] . ' requires a serial or IMEI number.');
        }

        if (!empty($item['serial_numbers']) || !empty($item['imei_numbers'])) {
            $this->processSerialTracking($item, $dto->businessId, $lineId);
        }

        return $lineCogs;
    }

    private function processSerialTracking(array $item, int $businessId, int $lineId): void
    {
        $serials     = $item['serial_numbers'] ?? [];
        $imeis       = $item['imei_numbers'] ?? [];
        $maxTracking = max(count($serials), count($imeis));

        if ($maxTracking !== (int) $item['quantity']) {
            throw new \Exception('Serial count mismatch: got ' . $maxTracking . ', expected ' . $item['quantity']);
        }

        $exists = DB::table('transaction_item_serials')
            ->where(function ($q) use ($serials, $imeis) {
                if (!empty($serials)) $q->orWhereIn('serial_number', $serials);
                if (!empty($imeis))   $q->orWhereIn('imei_number', $imeis);
            })->exists();

        if ($exists) {
            throw new \Exception('FPM Security: Serial/IMEI already exists in active ledger.');
        }

        $available = DB::table('inventory_item_serials')
            ->where('business_id', $businessId)
            ->where('product_id', $item['product_id'])
            ->where('status', 'Available')
            ->where(function ($q) use ($serials, $imeis) {
                if (!empty($serials)) $q->orWhereIn('serial_number', $serials);
                if (!empty($imeis))   $q->orWhereIn('imei_number', $imeis);
            })->lockForUpdate()->get();

        if ($available->count() !== $maxTracking) {
            throw new \Exception('One or more selected serial/IMEI numbers are not in Available stock.');
        }

        for ($i = 0; $i < $maxTracking; $i++) {
            DB::table('transaction_item_serials')->insert([
                'business_id'         => $businessId,
                'transaction_item_id' => $lineId,
                'serial_number'       => $serials[$i] ?? $imeis[$i],
                'imei_number'         => $imeis[$i] ?? null,
                'created_at'          => Carbon::now(),
                'updated_at'          => Carbon::now(),
            ]);
        }

        DB::table('inventory_item_serials')
            ->whereIn('id', $available->pluck('id'))
            ->update(['status' => 'Sold', 'transaction_sell_line_id' => $lineId, 'updated_at' => Carbon::now()]);
    }

    private function postDoubleEntry(int $businessId, int $userId, string $invoiceNo, int $txId, $totals, $amountPaid, $amountDue, $totalCogs): void
    {
        $cash     = TenantAccountResolver::resolve($businessId, TenantAccountResolver::CASH);
        $ar       = TenantAccountResolver::resolve($businessId, TenantAccountResolver::AR);
        $sales    = TenantAccountResolver::resolve($businessId, TenantAccountResolver::SALES);
        $tax      = TenantAccountResolver::resolve($businessId, TenantAccountResolver::TAX_PAYABLE);
        $discount = TenantAccountResolver::resolve($businessId, TenantAccountResolver::DISCOUNT);
        $cogs     = TenantAccountResolver::resolve($businessId, TenantAccountResolver::COGS);
        $inv      = TenantAccountResolver::resolve($businessId, TenantAccountResolver::INVENTORY);

        $debits = $credits = [];
        $credits[] = ['chart_of_account_id' => $sales, 'amount' => (string) $totals->subtotal];
        if ($totals->taxAmount->isGreaterThan(0))    $credits[] = ['chart_of_account_id' => $tax,      'amount' => (string) $totals->taxAmount];
        if ($totals->discountValue->isGreaterThan(0)) $debits[]  = ['chart_of_account_id' => $discount, 'amount' => (string) $totals->discountValue];
        if ($amountPaid->isGreaterThan(0))            $debits[]  = ['chart_of_account_id' => $cash,     'amount' => (string) $amountPaid];
        if ($amountDue->isGreaterThan(0))             $debits[]  = ['chart_of_account_id' => $ar,       'amount' => (string) $amountDue];
        if ($totalCogs->isGreaterThan(0)) {
            $debits[]  = ['chart_of_account_id' => $cogs, 'amount' => (string) $totalCogs];
            $credits[] = ['chart_of_account_id' => $inv,  'amount' => (string) $totalCogs];
        }

        $this->doubleEntry->recordEntry(
            $businessId, $invoiceNo, Carbon::now()->toDateString(),
            "Sale #$invoiceNo", $debits, $credits, $txId, 'transaction', $userId
        );
    }

    private function awardLoyaltyPoints(int $businessId, int $contactId, int $txId, string $invoiceNo, $finalTotal): void
    {
        $points = (int) floor((float) (string) $finalTotal / 100);
        if ($points <= 0) return;

        $last    = DB::table('loyalty_point_ledgers')->where('contact_id', $contactId)->orderByDesc('id')->first();
        $balance = ($last->running_balance ?? 0) + $points;

        DB::table('loyalty_point_ledgers')->insert([
            'business_id'     => $businessId,
            'contact_id'      => $contactId,
            'transaction_id'  => $txId,
            'points_earned'   => $points,
            'points_redeemed' => 0,
            'running_balance' => $balance,
            'description'     => 'Points earned on Sale #' . $invoiceNo,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
    }
}
