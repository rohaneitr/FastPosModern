<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Services\DoubleEntryLedgerService;
use App\Modules\Manufacturing\Events\ProductionOrderUpdatedEvent;
use Illuminate\Support\Facades\DB;
use Exception;

class RecordProductionCostJournalEntry
{
    protected DoubleEntryLedgerService $ledger;

    public function __construct(DoubleEntryLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Synchronous Listener.
     * Fires immediately on 'Completed' status to ensure atomic ledger integration.
     */
    public function handle(ProductionOrderUpdatedEvent $event)
    {
        $order = $event->order;

        if ($order['status'] !== 'Completed') {
            return; // Only record financial footprint upon completion
        }

        // 1. Resolve Accounts dynamically based on tenant codes
        $accounts = DB::table('finance_accounts')
            ->where('business_id', $event->businessId)
            ->whereIn('code', ['1200', '1210', '2100', '5100']) // 1200: Raw Materials, 1210: Finished Goods, 2100: Accrued Payables, 5100: Direct Labor Exp
            ->pluck('id', 'code');

        if (count($accounts) < 4) {
            // If the COA is missing, we fail loudly to prevent unrecorded production.
            throw new Exception("Chart of Accounts incomplete. Missing essential Manufacturing accounts (1200, 1210, 2100, 5100).");
        }

        // 2. Build the Double-Entry Lines
        // Total Cost = Raw Materials + Labor + Overhead
        $rawMaterialCost = $order['total_material_cost'];
        $laborCost = $order['labor_cost'];
        $overheadCost = $order['overhead_cost'];
        
        $totalCost = bcadd($rawMaterialCost, bcadd($laborCost, $overheadCost, 4), 4);

        $lines = [];

        // Debit: Finished Goods Inventory increases by Total Cost
        $lines[] = [
            'account_id' => $accounts['1210'],
            'type' => 'debit',
            'amount' => $totalCost
        ];

        // Credit: Raw Materials Inventory decreases
        if (bccomp($rawMaterialCost, '0.0000', 4) > 0) {
            $lines[] = [
                'account_id' => $accounts['1200'],
                'type' => 'credit',
                'amount' => $rawMaterialCost
            ];
        }

        // Credit: Accrued Direct Labor Liability increases
        if (bccomp($laborCost, '0.0000', 4) > 0) {
            $lines[] = [
                'account_id' => $accounts['5100'], // Wait, 5100 is typically expense, but for capitalization we credit Accrued Wages (liability)
                'type' => 'credit',
                'amount' => $laborCost
            ];
        }

        // Credit: Accrued Overhead Payables increases
        if (bccomp($overheadCost, '0.0000', 4) > 0) {
            $lines[] = [
                'account_id' => $accounts['2100'],
                'type' => 'credit',
                'amount' => $overheadCost
            ];
        }

        // 3. Inject into Ledger
        $this->ledger->recordEntry(
            $event->businessId,
            'production_order',
            "PROD-" . $order['order_number'],
            "Capitalization of Manufacturing Costs for {$order['order_number']}",
            date('Y-m-d'), // Assume current date for production
            $lines
        );
    }
}
