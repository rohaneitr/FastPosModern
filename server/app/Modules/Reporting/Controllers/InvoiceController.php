<?php

namespace App\Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Generate a printable HTML invoice for a transaction.
     * Can be rendered to PDF by the browser's native print-to-PDF,
     * or consumed by a frontend PDF library.
     */
    public function show(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $transaction = DB::table('transactions')
            ->leftJoin('users', 'transactions.created_by', '=', 'users.id')
            ->leftJoin('locations', 'transactions.location_id', '=', 'locations.id')
            ->leftJoin('businesses', 'transactions.business_id', '=', 'businesses.id')
            ->where('transactions.id', $id)
            ->where('transactions.business_id', $businessId)
            ->select(
                'transactions.*',
                'users.first_name as cashier_first', 'users.last_name as cashier_last',
                'locations.name as location_name',
                'businesses.name as business_name'
            )
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        $lines = DB::table('transaction_lines')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->where('transaction_lines.transaction_id', $id)
            ->select(
                'products.name as product_name', 'products.sku',
                'transaction_lines.quantity', 'transaction_lines.unit_price',
                'transaction_lines.unit_price_inc_tax', 'transaction_lines.item_tax'
            )
            ->get();

        $payments = DB::table('transaction_payments')
            ->where('transaction_id', $id)
            ->get();

        return response()->json([
            'invoice' => $transaction,
            'lines' => $lines,
            'payments' => $payments,
        ]);
    }

    /**
     * Render an HTML invoice suitable for printing or PDF export.
     */
    public function printView(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $transaction = DB::table('transactions')
            ->leftJoin('users', 'transactions.created_by', '=', 'users.id')
            ->leftJoin('locations', 'transactions.location_id', '=', 'locations.id')
            ->leftJoin('businesses', 'transactions.business_id', '=', 'businesses.id')
            ->where('transactions.id', $id)
            ->where('transactions.business_id', $businessId)
            ->select(
                'transactions.*',
                'users.first_name as cashier_first', 'users.last_name as cashier_last',
                'locations.name as location_name',
                'businesses.name as business_name'
            )
            ->first();

        if (!$transaction) {
            return response('Invoice not found', 404);
        }

        $lines = DB::table('transaction_lines')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->where('transaction_lines.transaction_id', $id)
            ->select('products.name', 'products.sku', 'transaction_lines.quantity',
                'transaction_lines.unit_price', 'transaction_lines.unit_price_inc_tax', 'transaction_lines.item_tax')
            ->get();

        $payments = DB::table('transaction_payments')->where('transaction_id', $id)->get();

        $html = view('invoices.receipt', [
            'tx' => $transaction,
            'lines' => $lines,
            'payments' => $payments,
        ])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
