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
            ->leftJoin('medicines_meta', 'products.id', '=', 'medicines_meta.product_id')
            ->leftJoin('prescriptions', 'transaction_lines.prescription_id', '=', 'prescriptions.id')
            ->where('transaction_lines.transaction_id', $id)
            ->select(
                'products.name as product_name', 'products.sku',
                'transaction_lines.quantity', 'transaction_lines.unit_price',
                'transaction_lines.unit_price_inc_tax', 'transaction_lines.item_tax',
                'transaction_lines.dosage_instructions', 'transaction_lines.warranty_duration',
                'transaction_lines.sourcing_status',
                'medicines_meta.generic_name',
                'prescriptions.doctor_name', 'prescriptions.patient_id'
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
            ->select('transaction_lines.id as line_id', 'products.name', 'products.sku', 'transaction_lines.quantity',
                'transaction_lines.unit_price', 'transaction_lines.unit_price_inc_tax', 'transaction_lines.item_tax', 'transaction_lines.warranty_duration')
            ->get();

        $lineIds = $lines->pluck('line_id')->toArray();
        $serials = DB::table('transaction_item_serials')
            ->whereIn('transaction_item_id', $lineIds)
            ->get()
            ->groupBy('transaction_item_id');

        foreach ($lines as $line) {
            $lineSerials = $serials->get($line->line_id, collect());
            $tracking = [];
            foreach ($lineSerials as $s) {
                if ($s->serial_number && $s->imei_number) {
                    $tracking[] = $s->serial_number . ' / ' . $s->imei_number;
                } elseif ($s->serial_number) {
                    $tracking[] = $s->serial_number;
                } elseif ($s->imei_number) {
                    $tracking[] = $s->imei_number;
                }
            }
            $line->tracking_numbers = $tracking;
        }

        $payments = DB::table('transaction_payments')->where('transaction_id', $id)->get();

        $html = view('invoices.receipt', [
            'tx' => $transaction,
            'lines' => $lines,
            'payments' => $payments,
        ])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
