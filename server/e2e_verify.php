<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$args = getopt('', ['product1:', 'product2:', 'txId:']);

$p1 = $args['product1'] ?? 0;
$p2 = $args['product2'] ?? 0;
$txId = $args['txId'] ?? 0;

$stocks = \Illuminate\Support\Facades\DB::table('product_stocks')->whereIn('product_id', [$p1, $p2])->get()->keyBy('product_id');

if ($txId) {
    $tx = \Illuminate\Support\Facades\DB::table('transactions')->where('id', $txId)->first();
    $tx_lines = \Illuminate\Support\Facades\DB::table('transaction_lines')->where('transaction_id', $txId)->count();
    $tx_payments = \Illuminate\Support\Facades\DB::table('transaction_payments')->where('transaction_id', $txId)->first();
} else {
    $tx = \Illuminate\Support\Facades\DB::table('transactions')->orderBy('id', 'desc')->take(1)->first();
    $tx_lines = $tx ? \Illuminate\Support\Facades\DB::table('transaction_lines')->where('transaction_id', $tx->id)->count() : 0;
    $tx_payments = $tx ? \Illuminate\Support\Facades\DB::table('transaction_payments')->where('transaction_id', $tx->id)->first() : null;
}

echo 'JSON_START' . json_encode([
    'stock1' => $stocks[$p1]->qty_available ?? null,
    'stock2' => $stocks[$p2]->qty_available ?? null,
    'tx_total' => $tx->final_total ?? null,
    'tx_lines_count' => $tx_lines ?? 0,
    'tx_payment_amount' => $tx_payments->amount ?? 0,
    'supplier_ledgers' => \Illuminate\Support\Facades\DB::table('supplier_ledgers')->count(), // Just to assert isolation
]) . 'JSON_END';
