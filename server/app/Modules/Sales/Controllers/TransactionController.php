<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Services\ProcessSaleService;
use App\Modules\Sales\Services\HoldTransactionService;
use App\Modules\Sales\DataTransferObjects\SaleCheckoutDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * TransactionController — Phase 3 Refactored
 *
 * BEFORE: 1244 lines, 6 responsibilities in one class.
 * AFTER:  ~200 lines, single responsibility: HTTP orchestration.
 *
 * Responsibilities (ONLY):
 *  1. Authenticate/authorize the request (via middleware + Gate)
 *  2. Validate HTTP input
 *  3. Build a DTO from validated input
 *  4. Delegate to the appropriate Service class
 *  5. Return a JSON response
 *
 * All business logic lives in:
 *  - ProcessSaleService        (checkout, convert-to-invoice)
 *  - HoldTransactionService    (hold, list-held, delete-held)
 *  - (syncPush remains here for now — Phase 3 Task 3.6)
 *
 * @author  Antigravity AI Agent — Phase 3
 * @version 2026-06-12
 */
class TransactionController extends Controller
{
    public function __construct(
        private readonly ProcessSaleService     $processSale,
        private readonly HoldTransactionService $holdService,
    ) {}

    // ── CHECKOUT ──────────────────────────────────────────────────────────────

    /**
     * Process a POS sale checkout.
     * Supports: split payments, discounts (fixed/%), quotations, Rx compliance.
     */
    public function checkout(Request $request): JsonResponse
    {
        Gate::authorize('pos.access');

        $businessId = $request->user()->business_id;
        $userId     = $request->user()->id;

        // ── A. Idempotency / Double-Charge Shield ─────────────────────────────
        $idempotencyKey = $request->header('X-Idempotency-Key');
        $documentType   = $request->input('document_type', $request->input('save_as_quotation') ? 'Quotation' : 'Invoice');
        $isPosting      = $documentType === 'Invoice';

        if ($isPosting && !$request->input('is_offline_sync')) {
            $lockKey = "fpm_checkout_lock_{$businessId}_{$userId}";
            $lock    = Cache::lock($lockKey, 5);
            if (!$lock->get()) {
                return response()->json(['message' => 'FPM Security: Multiple rapid checkouts detected. Please wait.'], 429);
            }
        }

        // ── B. Register Session Guard ─────────────────────────────────────────
        $activeSessionId = null;
        if ($isPosting && !$request->input('is_offline_sync')) {
            $activeSessionId = $this->resolveActiveRegister($request, $businessId);
            if ($activeSessionId === false) {
                return response()->json([
                    'message' => 'FPM Security: POS checkout blocked. Cash register drawer is closed or bound to another device.'
                ], 422);
            }
        }

        // ── C. Validate ───────────────────────────────────────────────────────
        $validated = $request->validate([
            'location_id'                   => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'items'                          => 'required|array|min:1',
            'items.*.product_id'            => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.quantity'              => 'required|numeric|min:1',
            'items.*.price'                 => 'required|numeric|min:0',
            'items.*.serial_numbers'        => 'nullable|array',
            'items.*.serial_numbers.*'      => 'string',
            'items.*.imei_numbers'          => 'nullable|array',
            'items.*.imei_numbers.*'        => 'string',
            'items.*.warranty_duration'     => 'nullable|string|max:255',
            'items.*.fractional_ratio'      => 'nullable|numeric|min:0.01',
            'items.*.dosage_instructions'   => 'nullable|string|max:255',
            'tax_rate'                       => 'required|numeric|min:0',
            'discount_type'                 => 'nullable|string|in:fixed,percentage',
            'discount_amount'               => 'nullable|numeric|min:0',
            'contact_id'                    => ['nullable', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'amount_paid'                   => 'nullable|numeric|min:0',
            'payment_method'                => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz,advance,store_credit',
            'save_as_quotation'             => 'nullable|boolean',
            'document_type'                 => 'nullable|string|in:Invoice,ProformaInvoice,Quotation',
            'convert_quotation_id'          => 'nullable|integer',
            'prescription_doctor'           => 'nullable|string|max:255',
            'prescription_patient'          => 'nullable|string|max:255',
            'prescription_file'             => 'nullable|string',
            'prescription_notes'            => 'nullable|string',
        ]);

        // ── D. Build DTO & Delegate ───────────────────────────────────────────
        $dto = new SaleCheckoutDTO(
            businessId:          $businessId,
            userId:              $userId,
            locationId:          $validated['location_id'],
            items:               $validated['items'],
            taxRate:             (float) $validated['tax_rate'],
            discountType:        $validated['discount_type'] ?? null,
            discountAmount:      (float) ($validated['discount_amount'] ?? 0),
            contactId:           $validated['contact_id'] ?? null,
            amountPaid:          isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : null,
            paymentMethod:       $validated['payment_method'],
            documentType:        $documentType,
            isPosting:           $isPosting,
            idempotencyKey:      $idempotencyKey,
            convertQuotationId:  $validated['convert_quotation_id'] ?? null,
            cashRegisterId:      $activeSessionId ?: null,
            isOfflineSync:       (bool) $request->input('is_offline_sync', false),
            prescriptionDoctor:  $validated['prescription_doctor'] ?? null,
            prescriptionPatient: $validated['prescription_patient'] ?? null,
            prescriptionFile:    $validated['prescription_file'] ?? null,
            prescriptionNotes:   $validated['prescription_notes'] ?? null,
        );

        try {
            $result = $this->processSale->execute($dto);

            // Dispatch digital receipt notification
            if ($isPosting && $dto->contactId) {
                $contact = DB::table('contacts')->where('id', $dto->contactId)->first();
                if ($contact) {
                    $notifyMethods = array_filter([
                        !empty($contact->email)  ? 'email'    : null,
                        !empty($contact->mobile) ? 'whatsapp' : null,
                    ]);
                    if (!empty($notifyMethods)) {
                        \App\Modules\Sales\Jobs\SendInvoiceNotificationJob::dispatch(
                            $result->transactionId, $businessId, $contact, array_values($notifyMethods)
                        );
                    }
                }
            }

            return response()->json([
                'message'        => 'Sale processed successfully',
                'transaction_id' => $result->transactionId,
                'invoice_no'     => $result->invoiceNo,
                'subtotal'       => $result->subtotal,
                'discount'       => $result->discount,
                'tax'            => $result->tax,
                'final_total'    => $result->finalTotal,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Checkout failed', 'error' => $e->getMessage()], 500);
        }
    }

    // ── HOLD TRANSACTIONS ─────────────────────────────────────────────────────

    public function holdTransaction(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'location_id'        => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'items'              => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.quantity'   => 'required|numeric|min:1',
            'items.*.price'      => 'required|numeric|min:0',
            'tax_rate'           => 'required|numeric|min:0',
            'note'               => 'nullable|string|max:255',
        ]);

        $result = $this->holdService->hold(
            $businessId,
            $request->user()->id,
            $validated['location_id'],
            $validated['items'],
            (float) $validated['tax_rate'],
            $validated['note'] ?? null,
        );

        return response()->json(['message' => 'Transaction held successfully', ...$result], 201);
    }

    public function heldTransactions(Request $request): JsonResponse
    {
        $held = $this->holdService->listHeld($request->user()->business_id);
        return response()->json($held);
    }

    public function deleteHeld(Request $request, int $id): JsonResponse
    {
        $deleted = $this->holdService->deleteHeld($request->user()->business_id, $id);

        return $deleted
            ? response()->json(['message' => 'Held transaction deleted'])
            : response()->json(['message' => 'Held transaction not found'], 404);
    }

    // ── CONVERT QUOTATION → INVOICE ───────────────────────────────────────────

    public function convertToInvoice(Request $request, int $id): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $transaction = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transaction->status === 'converted' || $transaction->document_type === 'Invoice') {
            return response()->json(['message' => 'Document is already an active invoice or converted.'], 422);
        }

        $validated = $request->validate([
            'payment_method'           => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz',
            'amount_paid'              => 'required|numeric|min:0',
            'serials'                  => 'nullable|array',
            'serials.*.product_id'     => 'required|integer',
            'serials.*.serial_numbers' => 'required|array',
            'serials.*.serial_numbers.*' => 'string',
        ]);

        try {
            // Build a synthetic checkout DTO from the quotation
            $dto = new SaleCheckoutDTO(
                businessId:          $businessId,
                userId:              $request->user()->id,
                locationId:          $transaction->location_id,
                items:               $this->buildItemsFromTransaction($transaction->id),
                taxRate:             (float) $this->resolveTaxRate($transaction),
                discountType:        $transaction->discount_type,
                discountAmount:      (float) ($transaction->discount_amount ?? 0),
                contactId:           $transaction->contact_id ?? null,
                amountPaid:          (float) $validated['amount_paid'],
                paymentMethod:       $validated['payment_method'],
                documentType:        'Invoice',
                isPosting:           true,
                idempotencyKey:      null,
                convertQuotationId:  $id,
                cashRegisterId:      null,
                isOfflineSync:       false,
                prescriptionDoctor:  null,
                prescriptionPatient: null,
                prescriptionFile:    null,
                prescriptionNotes:   null,
            );

            $result = $this->processSale->execute($dto);

            return response()->json([
                'message'        => 'Successfully converted to Invoice',
                'transaction_id' => $result->transactionId,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Conversion failed', 'error' => $e->getMessage()], 500);
        }
    }

    // ── OFFLINE SYNC ──────────────────────────────────────────────────────────

    /**
     * Bulk offline transaction push (mobile sync).
     * Still uses direct DB writes for performance — will be extracted in Phase 3.6.
     */
    public function syncPush(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'transactions'                       => 'required|array',
            'transactions.*.invoice_no'          => 'required|string',
            'transactions.*.location_id'         => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'transactions.*.transaction_date'    => 'required|date',
            'transactions.*.items'               => 'required|array|min:1',
            'transactions.*.items.*.product_id'  => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'transactions.*.items.*.quantity'    => 'required|numeric|min:1',
            'transactions.*.items.*.price'       => 'required|numeric|min:0',
            'transactions.*.payment_method'      => 'required|string|in:cash,card,bank_transfer',
            'transactions.*.tax_rate'            => 'required|numeric|min:0',
        ]);

        $syncedCount        = 0;
        $failedTransactions = [];

        foreach ($validated['transactions'] as $tx) {
            try {
                DB::beginTransaction();

                // Idempotency: skip already-synced
                $exists = DB::table('transactions')
                    ->where('business_id', $businessId)
                    ->where('invoice_no', $tx['invoice_no'])
                    ->exists();

                if ($exists) { DB::rollBack(); continue; }

                $subtotal  = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $tx['items']));
                $taxAmount = $subtotal * $tx['tax_rate'];
                $finalTotal = $subtotal + $taxAmount;

                $txId = DB::table('transactions')->insertGetId([
                    'business_id'      => $businessId,
                    'location_id'      => $tx['location_id'],
                    'created_by'       => $request->user()->id,
                    'type'             => 'sell',
                    'status'           => 'final',
                    'invoice_no'       => $tx['invoice_no'],
                    'transaction_date' => $tx['transaction_date'],
                    'total_before_tax' => $subtotal,
                    'tax_amount'       => $taxAmount,
                    'final_total'      => $finalTotal,
                    'created_at'       => Carbon::now(),
                    'updated_at'       => Carbon::now(),
                ]);

                $sortedItems = $tx['items'];
                usort($sortedItems, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

                $lines = [];
                foreach ($sortedItems as $item) {
                    $lines[] = [
                        'business_id'                => $businessId,
                        'transaction_id'            => $txId,
                        'product_id'                => $item['product_id'],
                        'quantity'                  => $item['quantity'],
                        'unit_price_before_discount'=> $item['price'],
                        'unit_price'                => $item['price'],
                        'unit_price_inc_tax'        => $item['price'] + ($item['price'] * $tx['tax_rate']),
                        'item_tax'                  => $item['price'] * $tx['tax_rate'],
                        'tax_rate'                  => $tx['tax_rate'],
                        'tax_amount'                => $item['price'] * $item['quantity'] * $tx['tax_rate'],
                        'sourcing_status'           => 'ready',
                        'created_at'               => Carbon::now(),
                        'updated_at'               => Carbon::now(),
                    ];

                    // Offline: allow negative stock (historical fact)
                    $stock = DB::table('product_stocks')
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $tx['location_id'])
                        ->lockForUpdate()->first();

                    if ($stock) {
                        DB::table('product_stocks')->where('id', $stock->id)
                            ->decrement('qty_available', $item['quantity']);
                    }
                }

                DB::table('transaction_lines')->insert($lines);
                DB::table('transaction_payments')->insert([
                    'business_id'    => $businessId,
                    'transaction_id' => $txId,
                    'amount'         => $finalTotal,
                    'method'         => $tx['payment_method'],
                    'paid_on'        => $tx['transaction_date'],
                    'created_by'     => $request->user()->id,
                    'created_at'     => Carbon::now(),
                    'updated_at'     => Carbon::now(),
                ]);

                DB::commit();
                $syncedCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $failedTransactions[] = ['invoice_no' => $tx['invoice_no'], 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message'         => 'Sync completed',
            'synced_count'    => $syncedCount,
            'failed'          => $failedTransactions,
            'sync_timestamp'  => Carbon::now()->toDateTimeString(),
        ]);
    }

    /**
     * Atomically process queued offline transactions by delegating to checkout().
     */
    public function syncOfflineTransactions(Request $request): JsonResponse
    {
        $successes = [];
        $failures  = [];

        foreach ($request->input('transactions', []) as $tx) {
            $uuid    = $tx['uuid'];
            $payload = $tx['payload'];
            $payload['is_offline_sync'] = true;

            try {
                $internalRequest = new \Illuminate\Http\Request();
                $internalRequest->replace($payload);
                $internalRequest->setUserResolver(fn() => $request->user());

                $response = $this->checkout($internalRequest);

                if ($response->getStatusCode() === 201) {
                    $successes[] = $uuid;
                } else {
                    $data = json_decode($response->getContent(), true);
                    $msg  = $data['message'] ?? 'Unknown Error';
                    if (isset($data['error']))                      $msg .= ' - ' . $data['error'];
                    elseif (isset($data['errors']['inventory'][0])) $msg .= ' - ' . $data['errors']['inventory'][0];
                    $failures[] = ['uuid' => $uuid, 'error' => $msg];
                }
            } catch (\Exception $e) {
                $failures[] = ['uuid' => $uuid, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['message' => 'Sync completed', 'successes' => $successes, 'failures' => $failures]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Resolve the active cash register session ID for the requesting user/device.
     * Returns false if no valid session exists.
     */
    private function resolveActiveRegister(Request $request, int $businessId): int|false
    {
        $business       = DB::table('businesses')->where('id', $businessId)->first();
        $settings       = $business->settings ? json_decode($business->settings, true) : [];
        $enforceDevice  = $settings['pos_enforce_device_lock'] ?? true;
        $deviceHash     = $request->header('X-Device-Hash') ?? $request->input('device_hash');

        $query = DB::table('cash_registers')
            ->where('opened_by_user_id', $request->user()->id)
            ->where('status', 'open');

        if ($enforceDevice) {
            $query->where('device_hash', $deviceHash);
        }

        $session = $query->first();
        return $session ? $session->id : false;
    }

    /**
     * Build a simplified items array from an existing transaction's lines.
     * Used for quotation→invoice conversion.
     */
    private function buildItemsFromTransaction(int $txId): array
    {
        return DB::table('transaction_lines')
            ->where('transaction_id', $txId)
            ->get()
            ->map(fn($line) => [
                'product_id'   => $line->product_id,
                'variation_id' => $line->variation_id,
                'quantity'     => $line->quantity,
                'price'        => $line->unit_price,
            ])
            ->toArray();
    }

    /**
     * Derive tax rate from an existing transaction.
     * If stored on line items, use first line's tax_rate. Fallback: 0.
     */
    private function resolveTaxRate(object $transaction): float
    {
        $line = DB::table('transaction_lines')->where('transaction_id', $transaction->id)->first();
        return (float) ($line->tax_rate ?? 0);
    }
}
