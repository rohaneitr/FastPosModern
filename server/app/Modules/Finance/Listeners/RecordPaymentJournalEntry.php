<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Services\DoubleEntryLedgerService;
use App\Modules\Finance\Events\PaymentReceivedEvent;
use Illuminate\Support\Facades\DB;
use Exception;

class RecordPaymentJournalEntry
{
    protected DoubleEntryLedgerService $ledger;

    public function __construct(DoubleEntryLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Synchronously records the cash inflow into the General Ledger.
     */
    public function handle(PaymentReceivedEvent $event)
    {
        $payload = $event->payload;
        $businessId = $payload['businessId'];
        $amountPaid = (string)$payload['amount'];
        $transactionId = $payload['transactionId'];

        // 1. Resolve Accounts
        // 1000 = Cash/Bank (Asset)
        // 1100 = Accounts Receivable (Asset)
        $accounts = DB::table('finance_accounts')
            ->where('business_id', $businessId)
            ->whereIn('code', ['1000', '1100'])
            ->pluck('id', 'code');

        if (count($accounts) < 2) {
            throw new Exception("Chart of Accounts missing Cash/AR accounts (1000, 1100).");
        }

        $lines = [];

        // Debit: Cash/Bank Account (Asset increases)
        $lines[] = [
            'account_id' => $accounts['1000'],
            'type' => 'debit',
            'amount' => $amountPaid
        ];

        // Credit: Accounts Receivable (Asset decreases)
        $lines[] = [
            'account_id' => $accounts['1100'],
            'type' => 'credit',
            'amount' => $amountPaid
        ];

        // Ensure transaction is recorded in the ledger
        $this->ledger->recordEntry(
            $businessId,
            'payment_receipt',
            "PAY-{$transactionId}",
            "Payment Received via {$payload['gateway']} (Ref: {$payload['reference']})",
            date('Y-m-d'),
            $lines
        );
    }
}
