<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Actions\CalculateSaleTotalsAction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * HoldTransactionService
 *
 * Extracted from TransactionController::holdTransaction(), heldTransactions(),
 * and deleteHeld() methods.
 *
 * @author  Antigravity AI Agent — Phase 3
 * @version 2026-06-12
 */
class HoldTransactionService
{
    public function __construct(
        private readonly CalculateSaleTotalsAction $calculateTotals,
    ) {}

    /**
     * Park a sale as a draft for later resumption.
     */
    public function hold(int $businessId, int $userId, int $locationId, array $items, float $taxRate, ?string $note): array
    {
        $totals = $this->calculateTotals->execute($items, $taxRate, null, 0);

        $txId = DB::table('transactions')->insertGetId([
            'business_id'      => $businessId,
            'location_id'      => $locationId,
            'created_by'       => $userId,
            'type'             => 'sell',
            'status'           => 'draft',
            'invoice_no'       => 'HOLD-' . time() . '-' . mt_rand(100, 999),
            'transaction_date' => Carbon::now(),
            'total_before_tax' => (string) $totals->afterDiscount,
            'tax_amount'       => (string) $totals->taxAmount,
            'final_total'      => (string) $totals->finalTotal,
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);

        $lines = [];
        foreach ($totals->enrichedItems as $item) {
            $lines[] = [
                'business_id'                => $businessId,
                'transaction_id'            => $txId,
                'product_id'                => $item['product_id'],
                'variation_id'              => $item['variation_id'] ?? null,
                'quantity'                  => $item['quantity'],
                'unit_price_before_discount'=> $item['price'],
                'unit_price'                => $item['price'],
                'unit_price_inc_tax'        => $item['price'] + ($item['price'] * $taxRate),
                'item_tax'                  => $item['price'] * $taxRate,
                'tax_rate'                  => $taxRate,
                'tax_amount'                => $item['price'] * $item['quantity'] * $taxRate,
                'sourcing_status'           => 'ready',
                'created_at'               => Carbon::now(),
                'updated_at'               => Carbon::now(),
            ];
        }
        DB::table('transaction_lines')->insert($lines);

        return ['transaction_id' => $txId];
    }

    /**
     * List all held (draft) transactions for a business.
     */
    public function listHeld(int $businessId): array
    {
        $held = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'draft')
            ->orderByDesc('created_at')
            ->get();

        if ($held->isEmpty()) return $held->all();

        $heldIds  = $held->pluck('id')->toArray();
        $allLines = DB::table('transaction_lines')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->whereIn('transaction_lines.transaction_id', $heldIds)
            ->select('transaction_lines.*', 'products.name as product_name')
            ->get()
            ->groupBy('transaction_id');

        foreach ($held as &$tx) {
            $tx->lines = $allLines->get($tx->id, collect())->values()->all();
        }
        return $held->all();
    }

    /**
     * Cancel (delete) a held draft transaction.
     */
    public function deleteHeld(int $businessId, int $txId): bool
    {
        $tx = DB::table('transactions')
            ->where('id', $txId)
            ->where('business_id', $businessId)
            ->where('status', 'draft')
            ->first();

        if (!$tx) return false;

        DB::table('transaction_lines')->where('transaction_id', $txId)->delete();
        DB::table('transactions')->where('id', $txId)->delete();

        return true;
    }
}
