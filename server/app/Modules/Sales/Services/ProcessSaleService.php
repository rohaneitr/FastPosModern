<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Actions\CalculateSaleTotalsAction;
use App\Modules\Sales\DataTransferObjects\SaleCheckoutDTO;
use App\Modules\Sales\DataTransferObjects\SaleCheckoutResult;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Models\Sale;
use App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction;
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
 * @author  Antigravity AI Agent â€” Phase 3
 * @version 2026-06-12
 */
class ProcessSaleService
{
    public function __construct(
        private readonly CalculateSaleTotalsAction       $calculateTotals,
        private readonly ConsumeBatchFIFOInventoryAction $consumeFifo,
    ) {}

    /**
     * Execute the full sale checkout pipeline.
     *
     * @throws \Illuminate\Validation\ValidationException  â€” Stock insufficient
     * @throws \Exception                                  â€” Business rule violation
     */
    public function execute(SaleCheckoutDTO $dto): SaleCheckoutResult
    {
        // â”€â”€ 0. Idempotency Guard â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

        // â”€â”€ 1. Calculate Totals (Zero-Trust Pricing) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

        // â”€â”€ 2. Pharmacy Rx Shield â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

        // â”€â”€ 3. Credit sale guard â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($dto->isPosting && $amountDue->isGreaterThan(0.01) && empty($dto->contactId)) {
            throw new \Exception('Customer MUST be selected for credit sales / dues.');
        }

        // â”€â”€ 4. Advance / Store Credit balance checks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($dto->isPosting && $dto->paymentMethod === 'store_credit' && $amountPaid->isGreaterThan(0)) {
            $this->validateStoreCredit($dto->contactId, $amountPaid);
        }

        // â”€â”€ 5. Main DB Transaction â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

                // 5g. Accumulate COGS per line from pre-computed FIFO map
                // NOTE: Actual stock deduction is handled by DeductStockFromSale listener.
                if ($dto->isPosting) {
                    $lineCogs  = FinancialCalculator::of($finalCogsMap[$item['product_id']] ?? 0);
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

            // 5j. Hydrate the Sale Eloquent model â€” listeners use it for relationships
            //     and readable IDs. We load without re-querying by constructing from
            //     the known state rather than calling Sale::findOrFail($txId) which
            //     would add a round-trip. The model is used read-only by listeners.
            $sale = Sale::findOrFail($txId);

            // 5k. Dispatch SaleCompleted domain event (SYNCHRONOUS â€” no ShouldQueue).
            //
            //     Registered listeners run inline here, still inside this transaction:
            //       1. DeductStockFromSale         â†’ product_stocks / serial ledger
            //       2. ApplyLoyaltyPointsFromSale  â†’ loyalty_point_ledgers
            //       3. RecordSaleJournalEntry       â†’ journal_entries / journal_lines
            //
            //     Any exception thrown by any listener propagates out of this closure
            //     and causes DB::transaction() to automatically issue a ROLLBACK,
            //     reverting the sale header, lines, payment, and all listener writes.
            event(new SaleCompleted(
                sale:       $sale,
                dto:        $dto,
                totals:     $totals,
                totalCogs:  $totalCogs,
                amountPaid: $amountPaid,
                amountDue:  $amountDue,
                invoiceNo:  $invoiceNo,
            ));

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

    // â”€â”€ Private Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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
            ->where('business_id', $dto->businessId)
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
                // Check if from composite â€” allow pending sourcing
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

    // â”€â”€ Methods removed in Phase 5 â€” Domain Event Decoupling â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //
    // The following private methods were extracted to domain listeners and
    // deleted from this service. ProcessSaleService now only owns persistence
    // of the sale record itself. All domain side-effects are handled by the
    // SaleCompleted event's registered listeners.
    //
    //   deductInventoryForLine()  â†’ App\Modules\Inventory\Listeners\DeductStockFromSale
    //   processSerialTracking()   â†’ App\Modules\Inventory\Listeners\DeductStockFromSale
    //   postDoubleEntry()         â†’ App\Modules\Accounting\Listeners\RecordSaleJournalEntry
    //   awardLoyaltyPoints()      â†’ App\Modules\CRM\Listeners\ApplyLoyaltyPointsFromSale

}

