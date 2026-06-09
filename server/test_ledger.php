<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$debits = DB::table('journal_lines')->where('type', 'debit')->sum('amount');
$credits = DB::table('journal_lines')->where('type', 'credit')->sum('amount');

echo "Debits: $debits\n";
echo "Credits: $credits\n";
echo "Difference: " . ($debits - $credits) . "\n";

$transactions = DB::table('transactions')->where('status', 'final')->take(3)->get();
foreach ($transactions as $tx) {
    echo "\nTransaction ID: " . $tx->id . " | Total: " . $tx->final_total . "\n";
    $je = DB::table('journal_entries')->where('model_type', 'transaction')->where('model_id', $tx->id)->first();
    if ($je) {
        $je_debits = DB::table('journal_lines')->where('journal_entry_id', $je->id)->where('type', 'debit')->sum('amount');
        echo "Journal Entry ID: " . $je->id . " | Total Debits: " . $je_debits . "\n";
    } else {
        echo "NO JOURNAL ENTRY FOUND!\n";
    }
}
