<?php

namespace App\Modules\Sales\Listeners;

use App\Modules\Finance\Services\DoubleEntryLedgerService;
use Illuminate\Support\Facades\DB;
use Exception;

class RecordSaleJournalEntry
{
    protected DoubleEntryLedgerService $ledger;

    public function __construct(DoubleEntryLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Synchronous Event Listener. 
     * Hooks into the operational Transaction process and writes the dual ledger footprint.
     * 
     * Expects an event object with: 
     * businessId, transactionId, amountPaid, subtotal, taxAmount, totalCogs
     */
    public function handle($event)
    {
        $businessId = $event->businessId;
        $transactionId = $event->transactionId;
        
        // Ensure values are strings for bcmath safety
        $amountPaid = (string)$event->amountPaid;
        $subtotal = (string)$event->subtotal;
        $taxAmount = (string)$event->taxAmount;
        $totalCogs = (string)$event->totalCogs;

        // 1. Resolve Accounts strictly.
        // Assuming a standard COA where:
        // 1000 = Cash, 1200 = Inventory Asset, 2200 = Sales Tax Payable, 4000 = Sales Revenue, 5000 = COGS
        $accounts = DB::table('finance_accounts')
            ->where('business_id', $businessId)
            ->whereIn('code', ['1000', '1200', '2200', '4000', '5000'])
            ->pluck('id', 'code');

        if (count($accounts) < 5) {
            throw new Exception("Chart of Accounts incomplete. Missing core accounts for sales transaction. Found: " . count($accounts));
        }

        $lines = [];

        // --- THE SALES ENTRY ---
        // Debit: Cash/Bank (Asset)
        $lines[] = [
            'account_id' => $accounts['1000'],
            'type' => 'debit',
            'amount' => $amountPaid
        ];

        // Credit: Sales Revenue
        if (bccomp($subtotal, '0.0000', 4) > 0) {
            $lines[] = [
                'account_id' => $accounts['4000'],
                'type' => 'credit',
                'amount' => $subtotal
            ];
        }

        // Credit: VAT / Sales Tax Payable (Liability)
        if (bccomp($taxAmount, '0.0000', 4) > 0) {
            $lines[] = [
                'account_id' => $accounts['2200'],
                'type' => 'credit',
                'amount' => $taxAmount
            ];
        }

        // --- THE INVENTORY/COGS ENTRY ---
        // Debit: Cost of Goods Sold (Expense)
        if (bccomp($totalCogs, '0.0000', 4) > 0) {
            $lines[] = [
                'account_id' => $accounts['5000'],
                'type' => 'debit',
                'amount' => $totalCogs
            ];

            // Credit: Inventory Asset (Asset reduction)
            $lines[] = [
                'account_id' => $accounts['1200'],
                'type' => 'credit',
                'amount' => $totalCogs
            ];
        }

        // The DoubleEntryLedgerService strictly verifies (Debits == Credits).
        // Mathematically:
        // amountPaid == subtotal + taxAmount
        // totalCogs == totalCogs
        $this->ledger->recordEntry(
            $businessId,
            'pos_sale',
            "TXN-{$transactionId}",
            "POS Sale #{$transactionId}",
            date('Y-m-d'),
            $lines
        );
    }
}
