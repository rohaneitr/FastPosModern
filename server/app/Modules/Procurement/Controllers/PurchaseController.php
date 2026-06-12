<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\Purchase;
use App\Modules\Procurement\Requests\StorePurchaseRequest;
use App\Modules\Procurement\Services\ProcessPurchaseService;
use App\Modules\Procurement\Services\ReceivePurchaseService;
use App\Modules\Inventory\Models\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * PurchaseController — Phase 3 Refactored
 *
 * BEFORE: 561 lines, 3 domains, identical pricing blocks copy-pasted in store/update
 * AFTER:  ~120 lines, pure HTTP orchestration — validate → delegate → respond
 *
 * Delegated to:
 *   ProcessPurchaseService   — full PO lifecycle (FIFO + ledger + forensic audit)
 *   ReceivePurchaseService   — legacy WAC quick-receive flow
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.4
 * @version 2026-06-12
 */
class PurchaseController extends Controller
{
    public function __construct(
        private readonly ProcessPurchaseService  $processPurchase,
        private readonly ReceivePurchaseService  $receivePurchase,
    ) {}

    // ── LIST & SHOW ───────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        try {
            $purchases = Purchase::with(['contact', 'lines.product'])->latest()->get();
            return response()->json(['data' => $purchases]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch purchases', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Purchase $purchase): JsonResponse
    {
        try {
            return response()->json(['data' => $purchase->load(['contact', 'lines.product'])]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch purchase', 'error' => $e->getMessage()], 500);
        }
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        try {
            $purchase = $this->processPurchase->create(
                $request->user()->business_id,
                $request->user()->id,
                $request->validated(),
            );

            return response()->json(['message' => 'Purchase created successfully', 'data' => $purchase], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to create purchase', 'error' => $e->getMessage()], 500);
        }
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function update(StorePurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        try {
            $updated = $this->processPurchase->update(
                $request->user()->business_id,
                $request->user()->id,
                $purchase,
                $request->validated(),
            );

            return response()->json(['message' => 'Purchase updated successfully', 'data' => $updated]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to update purchase', 'error' => $e->getMessage()], 500);
        }
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function destroy(Purchase $purchase): JsonResponse
    {
        try {
            DB::transaction(function () use ($purchase) {
                if ($purchase->status === 'received') {
                    StockLedger::where('transaction_type', 'purchase')
                        ->where('transaction_id', $purchase->id)
                        ->delete();
                }
                $purchase->delete();
            });

            return response()->json(['message' => 'Purchase deleted successfully']);
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['message' => 'Cannot delete purchase because it is linked to other records.'], 409);
            }
            return response()->json(['message' => 'Failed to delete purchase', 'error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to delete purchase', 'error' => $e->getMessage()], 500);
        }
    }

    // ── QUICK RECEIVE (WAC flow) ──────────────────────────────────────────────

    /**
     * Legacy "quick receive" endpoint — updates WAC cost and stocks directly.
     * Uses the transactions table (not purchases table).
     */
    public function receive(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'supplier_id'          => ['required', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'location_id'          => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'reference_no'         => 'required|string|max:255',
            'lines'                => 'required|array|min:1',
            'lines.*.product_id'   => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'lines.*.variation_id' => 'nullable|integer',
            'lines.*.quantity'     => 'required|numeric|min:0.0001',
            'lines.*.unit_cost'    => 'required|numeric|min:0',
        ]);

        try {
            $transactionId = $this->receivePurchase->receive(
                $businessId,
                $request->user()->id,
                $validated['location_id'],
                $validated['supplier_id'],
                $validated['reference_no'],
                $validated['lines'],
            );

            return response()->json([
                'message'        => 'Purchase received successfully. WAC updated.',
                'transaction_id' => $transactionId,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to process purchase.', 'error' => $e->getMessage()], 500);
        }
    }
}
