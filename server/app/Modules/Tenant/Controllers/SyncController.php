<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Procurement\Models\Contact;
use App\Modules\Sales\DataTransferObjects\SaleCheckoutDTO;
use App\Modules\Sales\Services\ProcessSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * SyncController — Delta Sync Engine (Phase 6)
 *
 * Implements a two-phase, timestamp-based delta sync protocol between the
 * FastPOS backend and the Dexie.js offline database on the client.
 *
 * ── PULL (Server → Client) ─────────────────────────────────────────────────
 * Client sends: { since: "ISO8601 timestamp" }
 * Server returns: all records in core tables updated AFTER `since`.
 * Tenant isolation is guaranteed by the BelongsToBusiness Eloquent global
 * scope — it is NOT manually re-applied in query code here. This design
 * means adding a new table to the pull payload only requires listing the
 * Eloquent model; the scope is inherited automatically.
 *
 * ── PUSH (Client → Server) ─────────────────────────────────────────────────
 * Client sends: { last_pulled_at: "ISO8601", transactions: [...] }
 * Server processes: offline sale transactions via ProcessSaleService.
 * Conflict resolution: "Server Wins" on ALL mutable entities.
 *
 * ── CONFLICT POLICY ────────────────────────────────────────────────────────
 * A conflict occurs when:
 *   - A client attempts to update a mutable entity (e.g. product price)
 *   - AND the server's `updated_at` for that row is NEWER than the
 *     client's `last_pulled_at` timestamp (meaning the server has
 *     been updated since the client's last sync)
 *
 * Resolution: SERVER WINS. The client's update is REJECTED for that row.
 * The conflicted server record is returned in the `conflicts` array so the
 * client can overwrite its local Dexie store with the correct server state.
 *
 * Offline SALES are NOT subject to the conflict policy — they are new
 * transactions, not updates to existing records. They are processed via
 * the full ProcessSaleService pipeline (including SaleCompleted event
 * dispatch for stock deduction and journal posting).
 *
 * ── IDEMPOTENCY ────────────────────────────────────────────────────────────
 * Each offline transaction must carry a `client_uuid` (client-generated
 * UUID v4). The server records this in `idempotency_key` on the transaction.
 * Re-submitting the same UUID returns the original result without duplicating
 * the sale — the ProcessSaleService idempotency guard handles this.
 *
 * @version Phase 6 — Offline Sync Engine
 */
class SyncController extends Controller
{
    // ── Pull: tables returned in the delta payload ─────────────────────────────
    // Models must use the BelongsToBusiness trait for automatic tenant isolation.
    // Keys = the Dexie store name the client expects. Values = Eloquent model class.
    private const PULL_ENTITIES = [
        'products'   => Product::class,
        'categories' => Category::class,
        'brands'     => Brand::class,
        'units'      => Unit::class,
        'contacts'   => Contact::class,
    ];

    // Mutable entity tables that can be updated by a client push (non-sales).
    // Transactions/sales are explicitly excluded — they must go through ProcessSaleService.
    private const MUTABLE_ENTITIES = [
        // Empty for now — Phase 6 only handles offline SALES push.
        // Future: 'contacts' => Contact::class (e.g. cashier-created walk-in customer)
    ];

    public function __construct(
        private readonly ProcessSaleService $processSale,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // PULL — GET /api/v1/sync/pull
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return all records updated after `since` for the authenticated tenant.
     *
     * Request params:
     *   since (required) — ISO 8601 timestamp. Use "1970-01-01T00:00:00Z" for a full sync.
     *
     * Response envelope (ApiResponse trait):
     * {
     *   "success": true,
     *   "message": "Delta sync pull complete.",
     *   "data": {
     *     "server_timestamp": "ISO8601",  // Client must store this as its new `last_pulled_at`
     *     "products":   [...],
     *     "categories": [...],
     *     "brands":     [...],
     *     "units":      [...],
     *     "contacts":   [...]
     *   }
     * }
     */
    public function pull(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['required', 'date'],
        ]);

        // Capture server_timestamp BEFORE querying so the window is inclusive.
        // The client must use this value as its next `last_pulled_at`, not its
        // local clock (which may be skewed on mobile/tablet POS devices).
        $serverTimestamp = Carbon::now()->toIso8601String();
        $since           = Carbon::parse($request->query('since'));

        $payload = ['server_timestamp' => $serverTimestamp];

        // Each model benefits automatically from the BelongsToBusiness global scope.
        // No explicit `->where('business_id', ...)` is needed or wanted here —
        // adding it manually would create a double-where and is a maintenance trap.
        foreach (self::PULL_ENTITIES as $key => $modelClass) {
            $payload[$key] = $modelClass::query()
                ->where('updated_at', '>', $since)
                ->orderBy('updated_at', 'asc')  // Stable ordering for pagination (future)
                ->get();
        }

        return $this->successResponse($payload, 'Delta sync pull complete.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — POST /api/v1/sync/push
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Accept offline changes from the client and apply them server-side.
     *
     * Request body:
     * {
     *   "last_pulled_at": "ISO8601",       // Client's last successful pull timestamp
     *   "transactions": [                  // Offline sales created while disconnected
     *     {
     *       "client_uuid":      "uuid-v4", // Idempotency key
     *       "location_id":      1,
     *       "items":            [...],
     *       "tax_rate":         0,
     *       "discount_type":    null,
     *       "discount_amount":  0,
     *       "contact_id":       null,
     *       "amount_paid":      null,
     *       "payment_method":   "cash",
     *       "transaction_date": "ISO8601"  // Device timestamp (honored for historical record)
     *     }
     *   ]
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "synced":    ["uuid-1", "uuid-2"],
     *     "skipped":   ["uuid-3"],           // Already synced (idempotent re-push)
     *     "conflicts": [...],                // Server-wins conflict report
     *     "failed":    [{"uuid": ..., "error": ...}]
     *   }
     * }
     */
    public function push(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;
        $userId     = $request->user()->id;

        $validated = $request->validate([
            'last_pulled_at'                          => ['required', 'date'],
            'transactions'                            => ['nullable', 'array'],
            'transactions.*.client_uuid'              => ['required', 'string', 'max:255'],
            'transactions.*.location_id'              => ['required', 'integer', 'exists:locations,id'],
            'transactions.*.items'                    => ['required', 'array', 'min:1'],
            'transactions.*.items.*.product_id'       => ['required', 'integer'],
            'transactions.*.items.*.quantity'         => ['required', 'numeric', 'min:0.01'],
            'transactions.*.items.*.variation_id'     => ['nullable', 'integer'],
            'transactions.*.items.*.serial_numbers'   => ['nullable', 'array'],
            'transactions.*.items.*.serial_numbers.*' => ['string'],
            'transactions.*.items.*.imei_numbers'     => ['nullable', 'array'],
            'transactions.*.items.*.imei_numbers.*'   => ['string'],
            'transactions.*.items.*.fractional_ratio' => ['nullable', 'numeric', 'min:0.01'],
            'transactions.*.tax_rate'                 => ['required', 'numeric', 'min:0'],
            'transactions.*.discount_type'            => ['nullable', 'string', 'in:fixed,percentage'],
            'transactions.*.discount_amount'          => ['nullable', 'numeric', 'min:0'],
            'transactions.*.contact_id'               => ['nullable', 'integer'],
            'transactions.*.amount_paid'              => ['nullable', 'numeric', 'min:0'],
            'transactions.*.payment_method'           => ['required', 'string', 'in:cash,card,bank_transfer,bkash'],
            'transactions.*.transaction_date'         => ['required', 'date'],
        ]);

        $lastPulledAt = Carbon::parse($validated['last_pulled_at']);
        $transactions = $validated['transactions'] ?? [];

        $synced    = [];
        $skipped   = [];
        $conflicts = [];
        $failed    = [];

        // ── PHASE 1: Conflict detection on MUTABLE_ENTITIES ──────────────────
        // Currently empty — reserved for Phase 6.1 (contact push).
        // When a client pushes a mutable entity update, we compare the server's
        // `updated_at` against `last_pulled_at`. If the server row is newer, it
        // means another session has updated it since the client last synced.
        // Server wins: the client's version is rejected and the server version
        // is returned in the conflicts array for the client to overwrite locally.
        //
        // Architecture note: this check does NOT need a DB::transaction because
        // it is purely a read + conflict log. The actual safe writes happen in
        // Phase 2 (offline sales) which uses ProcessSaleService's own transaction.

        // ── PHASE 2: Process offline sale transactions ─────────────────────────
        // Each offline sale is processed individually. Per-transaction try/catch
        // ensures one bad sale doesn't block the rest. ProcessSaleService::execute()
        // wraps its own DB::transaction() — we do NOT open an outer transaction
        // here because the idempotency guard must be able to commit individual
        // sales independently.
        foreach ($transactions as $tx) {
            $clientUuid = $tx['client_uuid'];

            try {
                // ── Idempotency: check if already synced ──────────────────────
                // Uses the raw query builder deliberately — the transactions table
                // uses BelongsToBusiness scope on the Eloquent Sale model, but we
                // need a cross-guard lookup that explicitly scopes to business_id
                // without triggering the global scope's auth()->user() resolution
                // (which may behave unexpectedly inside a sync loop).
                $existingTx = DB::table('transactions')
                    ->where('business_id', $businessId)
                    ->where('idempotency_key', $clientUuid)
                    ->select('id', 'invoice_no', 'final_total')
                    ->first();

                if ($existingTx) {
                    // Already processed — return idempotent success, do not re-process.
                    $skipped[] = [
                        'client_uuid'    => $clientUuid,
                        'transaction_id' => $existingTx->id,
                        'invoice_no'     => $existingTx->invoice_no,
                    ];
                    continue;
                }

                // ── Location tenant isolation guard ───────────────────────────
                // Validate that the claimed location_id actually belongs to this
                // tenant. This cannot be done in the Validation rule above because
                // `exists:locations,id` doesn't cross-check business_id.
                $locationOwned = DB::table('locations')
                    ->where('id', $tx['location_id'])
                    ->where('business_id', $businessId)
                    ->exists();

                if (! $locationOwned) {
                    $failed[] = [
                        'client_uuid' => $clientUuid,
                        'error'       => 'Location does not belong to your business.',
                    ];
                    continue;
                }

                // ── Product tenant isolation guard ────────────────────────────
                // Verify every product_id in the cart belongs to this business.
                $submittedProductIds = collect($tx['items'])->pluck('product_id')->unique()->values()->toArray();
                $ownedProductCount   = DB::table('products')
                    ->where('business_id', $businessId)
                    ->whereIn('id', $submittedProductIds)
                    ->count();

                if ($ownedProductCount !== count($submittedProductIds)) {
                    $failed[] = [
                        'client_uuid' => $clientUuid,
                        'error'       => 'One or more products do not belong to your business.',
                    ];
                    continue;
                }

                // ── Build DTO from offline payload ────────────────────────────
                // isOfflineSync = true → bypasses the cash register session guard
                // in TransactionController. The register guard is irrelevant for
                // transactions that happened offline before the device reconnected.
                //
                // transactionDate = client's device timestamp → the sale is recorded
                // at the moment it actually happened, not when it was synced.
                //
                // idempotencyKey = client_uuid → ProcessSaleService uses this to
                // prevent double-processing if the push is retried.
                $dto = new SaleCheckoutDTO(
                    businessId:          $businessId,
                    userId:              $userId,
                    locationId:          (int) $tx['location_id'],
                    items:               $tx['items'],
                    taxRate:             (float) $tx['tax_rate'],
                    discountType:        $tx['discount_type'] ?? null,
                    discountAmount:      (float) ($tx['discount_amount'] ?? 0),
                    contactId:           isset($tx['contact_id']) ? (int) $tx['contact_id'] : null,
                    amountPaid:          isset($tx['amount_paid']) ? (float) $tx['amount_paid'] : null,
                    paymentMethod:       $tx['payment_method'],
                    documentType:        'Invoice',
                    isPosting:           true,
                    idempotencyKey:      $clientUuid,
                    convertQuotationId:  null,
                    cashRegisterId:      null,
                    isOfflineSync:       true,
                    prescriptionDoctor:  $tx['prescription_doctor'] ?? null,
                    prescriptionPatient: $tx['prescription_patient'] ?? null,
                    prescriptionFile:    $tx['prescription_file']   ?? null,
                    prescriptionNotes:   $tx['prescription_notes']  ?? null,
                    transactionDate:     $tx['transaction_date'],
                );

                // ── Execute full sale pipeline ────────────────────────────────
                // ProcessSaleService::execute() handles:
                //   - Idempotency guard (checks idempotency_key in DB)
                //   - Zero-trust pricing (re-fetches prices from DB)
                //   - DB::transaction() wrapping the entire sale
                //   - SaleCompleted event → DeductStockFromSale, RecordSaleJournalEntry,
                //     ApplyLoyaltyPointsFromSale (all synchronous, all rollback-safe)
                $result = $this->processSale->execute($dto);

                $synced[] = [
                    'client_uuid'    => $clientUuid,
                    'transaction_id' => $result->transactionId,
                    'invoice_no'     => $result->invoiceNo,
                    'final_total'    => $result->finalTotal,
                ];

            } catch (ValidationException $e) {
                // Inventory/business-rule rejection — not a server error
                $failed[] = [
                    'client_uuid' => $clientUuid,
                    'error'       => collect($e->errors())->flatten()->first()
                                     ?? 'Validation failed.',
                ];

                Log::warning('OfflineSync: validation failure', [
                    'client_uuid' => $clientUuid,
                    'business_id' => $businessId,
                    'errors'      => $e->errors(),
                ]);

            } catch (\Exception $e) {
                $failed[] = [
                    'client_uuid' => $clientUuid,
                    'error'       => $e->getMessage(),
                ];

                Log::error('OfflineSync: unexpected failure', [
                    'client_uuid' => $clientUuid,
                    'business_id' => $businessId,
                    'exception'   => $e->getMessage(),
                    'trace'       => $e->getTraceAsString(),
                ]);
            }
        }

        return $this->successResponse(
            [
                'synced'    => $synced,
                'skipped'   => $skipped,
                'conflicts' => $conflicts,
                'failed'    => $failed,
                'summary'   => [
                    'synced_count'    => count($synced),
                    'skipped_count'   => count($skipped),
                    'conflict_count'  => count($conflicts),
                    'failed_count'    => count($failed),
                ],
                'server_timestamp' => Carbon::now()->toIso8601String(),
            ],
            'Push sync completed.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────
    // successResponse() and errorResponse() are inherited from ApiResponse trait
    // (via App\Http\Controllers\Controller). No local override needed.
}
