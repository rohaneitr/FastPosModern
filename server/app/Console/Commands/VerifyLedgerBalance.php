<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyLedgerBalance extends Command
{
    protected $signature = 'ledger:verify';
    protected $description = 'Verifies that every journal entry in the system has balancing debits and credits.';

    public function handle()
    {
        $this->info("Starting Ledger Verification...");

        $corruptedCount = 0;
        $verifiedCount = 0;

        DB::table('finance_journal_entries')->orderBy('id')->chunkById(1000, function ($entries) use (&$corruptedCount, &$verifiedCount) {
            foreach ($entries as $entry) {
                // Get all lines for this entry
                $lines = DB::table('finance_journal_lines')
                    ->where('journal_entry_id', $entry->id)
                    ->get();

                $totalDebit = $lines->where('type', 'debit')->sum('amount');
                $totalCredit = $lines->where('type', 'credit')->sum('amount');

                // Floating point safe comparison
                if (abs($totalDebit - $totalCredit) > 0.0001) {
                    $corruptedCount++;
                    $errorMsg = "CORRUPTION DETECTED: Journal Entry #{$entry->id}. Debits: {$totalDebit}, Credits: {$totalCredit}. Diff: " . abs($totalDebit - $totalCredit);
                    
                    // Alert system monitor
                    Log::channel('single')->emergency($errorMsg);
                    $this->error($errorMsg);

                    // Flag as corrupted by prepending to description if not already there
                    if (!str_contains($entry->description ?? '', '[CORRUPTED]')) {
                        DB::table('finance_journal_entries')->where('id', $entry->id)->update([
                            'description' => '[CORRUPTED] ' . $entry->description
                        ]);
                    }
                } else {
                    $verifiedCount++;
                }
            }
        });

        $this->info("Verification Complete. Verified: {$verifiedCount}, Corrupted: {$corruptedCount}.");
        
        if ($corruptedCount > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
