<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class DoubleEntryLedgerService
{
    /**
     * Records a strict double-entry journal. 
     * Ensures Total Debits === Total Credits.
     * Must be called within a DB::transaction to ensure atomicity with the operational event.
     */
    public function recordEntry(
        int $businessId,
        string $referenceType,
        string $referenceId,
        string $description,
        string $date,
        array $lines
    ): int {
        $totalDebit = '0.0000';
        $totalCredit = '0.0000';

        foreach ($lines as $line) {
            if (!in_array($line['type'], ['debit', 'credit'])) {
                throw new Exception("Invalid journal line type: {$line['type']}");
            }

            if ($line['type'] === 'debit') {
                $totalDebit = bcadd($totalDebit, (string)$line['amount'], 4);
            } else {
                $totalCredit = bcadd($totalCredit, (string)$line['amount'], 4);
            }
        }

        // BRUTAL HONESTY: Mathematical Verification
        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw new Exception("Journal Entry Imbalance! Debits: {$totalDebit} != Credits: {$totalCredit}. Transaction aborted to protect ledger integrity.");
        }

        $entryId = DB::table('finance_journal_entries')->insertGetId([
            'business_id' => $businessId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'entry_date' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $insertLines = array_map(function ($line) use ($entryId) {
            return [
                'journal_entry_id' => $entryId,
                'account_id' => $line['account_id'],
                'type' => $line['type'],
                'amount' => $line['amount'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $lines);

        DB::table('finance_journal_lines')->insert($insertLines);

        return $entryId;
    }
}
