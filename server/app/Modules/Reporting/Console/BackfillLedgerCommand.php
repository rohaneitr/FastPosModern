<?php

namespace App\Modules\Reporting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Finance\Services\DoubleEntryEngine;
use App\Modules\Finance\Services\TenantAccountResolver;
use Carbon\Carbon;
use App\Modules\Sales\Services\FinancialCalculator;

class BackfillLedgerCommand extends Command
{
    protected $signature = 'ledger:backfill';
    protected $description = 'Backfill missing journal entries for historical transactions.';

    public function handle(DoubleEntryEngine $ledger)
    {
        $this->info("Scanning for transactions without journal entries...");

        $transactions = DB::table('transactions')
            ->where('status', 'final')
            ->where('final_total', '>', 0)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('journal_entries')
                      ->whereColumn('journal_entries.reference_id', 'transactions.id')
                      ->where('journal_entries.reference_type', 'transaction');
            })
            ->get();

        if ($transactions->isEmpty()) {
            $this->info("No missing ledger entries found for transactions with a positive final_total.");
            $this->info("Note: Transactions with a 0.00 total are intentionally skipped by the DoubleEntryEngine.");
            return 0;
        }

        $this->warn("Found " . $transactions->count() . " transactions missing ledger entries. Backfilling...");

        foreach ($transactions as $tx) {
            $businessId = $tx->business_id;

            $cashAccountId = TenantAccountResolver::resolve($businessId, TenantAccountResolver::CASH);
            $salesAccountId = TenantAccountResolver::resolve($businessId, TenantAccountResolver::SALES);
            $taxAccountId = TenantAccountResolver::resolve($businessId, TenantAccountResolver::TAX_PAYABLE);
            $discountAccountId = TenantAccountResolver::resolve($businessId, TenantAccountResolver::DISCOUNT);

            $debits = [];
            $credits = [];

            $subtotal = FinancialCalculator::of($tx->total_before_tax);
            $taxAmount = FinancialCalculator::of($tx->tax_amount);
            $discountAmount = FinancialCalculator::of($tx->discount_amount);
            $amountPaid = FinancialCalculator::of($tx->final_total); // Assume fully paid for backfill

            $credits[] = ['chart_of_account_id' => $salesAccountId, 'amount' => (string) $subtotal];

            if ($taxAmount->isGreaterThan(0)) {
                $credits[] = ['chart_of_account_id' => $taxAccountId, 'amount' => (string) $taxAmount];
            }
            if ($discountAmount->isGreaterThan(0)) {
                $debits[] = ['chart_of_account_id' => $discountAccountId, 'amount' => (string) $discountAmount];
            }
            if ($amountPaid->isGreaterThan(0)) {
                $debits[] = ['chart_of_account_id' => $cashAccountId, 'amount' => (string) $amountPaid];
            }

            try {
                $ledger->recordEntry(
                    $businessId,
                    $tx->invoice_no ?? 'SYS-'.$tx->id,
                    Carbon::parse($tx->transaction_date)->toDateString(),
                    "Backfilled Sale #".$tx->invoice_no,
                    $debits,
                    $credits,
                    $tx->id,
                    'transaction',
                    $tx->created_by
                );
                $this->info("Successfully backfilled transaction #{$tx->id}");
            } catch (\Exception $e) {
                $this->error("Failed to backfill transaction #{$tx->id}: " . $e->getMessage());
            }
        }

        $this->info("Backfill complete.");
        return 0;
    }
}
