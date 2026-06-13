<?php

namespace App\Modules\CRM\Listeners;

use App\Modules\Sales\Events\SaleCompleted;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ApplyLoyaltyPointsFromSale — CRM Domain Listener
 *
 * Awards loyalty points to the customer when a sale is completed.
 * Extracted from ProcessSaleService::awardLoyaltyPoints().
 *
 * SYNCHRONOUS — NO ShouldQueue.
 *
 * RESPONSIBILITY BOUNDARY:
 * This listener ONLY touches `loyalty_point_ledgers`. It does not
 * touch `transactions`, `product_stocks`, or `journal_entries`.
 *
 * POINT CALCULATION:
 * 1 point per every 100 units of currency in the final total.
 * e.g. ৳5,000 sale → 50 points.
 * This formula is intentionally simple and deterministic so that
 * the ledger balance is always auditable by inspecting the sale total.
 *
 * SKIP CONDITIONS:
 * - Draft / quotation sales (isPosting = false)
 * - Sales without a linked customer (contactId = null)
 * - Sales with a final total of zero (e.g. 100% discount)
 * - Point calculation results in zero or negative (no-op)
 *
 * @version Phase 5 — Domain Event Decoupling
 */
class ApplyLoyaltyPointsFromSale
{
    /**
     * Handle the SaleCompleted event.
     */
    public function handle(SaleCompleted $event): void
    {
        // Loyalty points only apply to finalized sales with a linked customer
        if (! $event->dto->isPosting) {
            return;
        }

        if (! $event->dto->contactId) {
            return;
        }

        if (! $event->totals->finalTotal->isGreaterThan(0)) {
            return;
        }

        // ── Calculate points ───────────────────────────────────────────────────
        // 1 point per 100 currency units (floor — no fractional points)
        $points = (int) floor((float) (string) $event->totals->finalTotal / 100);

        if ($points <= 0) {
            return;
        }

        $businessId = $event->dto->businessId;
        $contactId  = $event->dto->contactId;
        $txId       = $event->sale->id;
        $invoiceNo  = $event->invoiceNo;

        // ── Append to running-balance ledger ───────────────────────────────────
        // Reads the last ledger entry to get the current running balance.
        // lockForUpdate() prevents a race condition if two concurrent
        // checkouts for the same customer are processed simultaneously.
        $last = DB::table('loyalty_point_ledgers')
            ->where('contact_id', $contactId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $newBalance = ($last->running_balance ?? 0) + $points;

        DB::table('loyalty_point_ledgers')->insert([
            'business_id'     => $businessId,
            'contact_id'      => $contactId,
            'transaction_id'  => $txId,
            'points_earned'   => $points,
            'points_redeemed' => 0,
            'running_balance' => $newBalance,
            'description'     => 'Points earned on Sale #' . $invoiceNo,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
    }
}
