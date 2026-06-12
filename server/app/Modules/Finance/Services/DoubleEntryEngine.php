<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Exceptions\AccountingImbalanceException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Modules\Sales\Services\FinancialCalculator;
use Illuminate\Support\Facades\DB;

class DoubleEntryEngine
{
    /**
     * Record a balanced journal entry.
     * 
     * @param int $businessId
     * @param string $referenceNumber
     * @param string $date
     * @param string|null $narration
     * @param array $debits Array of ['chart_of_account_id' => x, 'amount' => y]
     * @param array $credits Array of ['chart_of_account_id' => x, 'amount' => y]
     * @param int|null $referenceId
     * @param string|null $referenceType
     * @param int|null $userId
     * @return JournalEntry
     * 
     * @throws AccountingImbalanceException
     */
    public function recordEntry(
        int $businessId,
        string $referenceNumber,
        string $date,
        ?string $narration,
        array $debits,
        array $credits,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?int $userId = null
    ): JournalEntry {
        // Validate Balancing
        $totalDebits = '0.0000';
        foreach ($debits as $debit) {
            $totalDebits = FinancialCalculator::add($totalDebits, $debit['amount']);
        }

        $totalCredits = '0.0000';
        foreach ($credits as $credit) {
            $totalCredits = FinancialCalculator::add($totalCredits, $credit['amount']);
        }

        // Must equal precisely
        if ((string)$totalDebits !== (string)$totalCredits) {
            throw new AccountingImbalanceException("Accounting entry is imbalanced. Debits ($totalDebits) do not equal Credits ($totalCredits).");
        }
        
        // Zero amounts not allowed? We can allow 0 if total is 0, but usually we just skip.
        // Let's create the entry
        return DB::transaction(function () use ($businessId, $referenceNumber, $date, $narration, $debits, $credits, $referenceId, $referenceType, $userId) {
            
            $entry = new JournalEntry([
                'business_id' => $businessId,
                'reference_number' => $referenceNumber,
                'date' => $date,
                'narration' => $narration,
                'created_by' => $userId,
            ]);

            if ($referenceId && $referenceType) {
                $entry->reference_id = $referenceId;
                $entry->reference_type = $referenceType;
            }

            $entry->save();

            $lines = [];
            
            foreach ($debits as $debit) {
                // Skip zero lines
                if (FinancialCalculator::of($debit['amount'])->isZero()) continue;

                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $debit['chart_of_account_id'],
                    'type' => 'debit',
                    'amount' => $debit['amount'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach ($credits as $credit) {
                if (FinancialCalculator::of($credit['amount'])->isZero()) continue;

                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $credit['chart_of_account_id'],
                    'type' => 'credit',
                    'amount' => $credit['amount'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($lines)) {
                JournalLine::insert($lines);
            }

            return $entry;
        });
    }
}
