<?php

namespace App\Domain\Sales\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class InvoicePdfService
{
    public function generateInvoicePdf($transactionId)
    {
        $transaction = DB::table('transactions')
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->leftJoin('businesses', 'transactions.business_id', '=', 'businesses.id')
            ->select('transactions.*', 'contacts.name as customer_name', 'contacts.email as customer_email', 'businesses.name as business_name', 'businesses.settings as business_settings')
            ->where('transactions.id', $transactionId)
            ->first();

        if (!$transaction) {
            throw new \Exception("Transaction not found");
        }

        $items = DB::table('transaction_lines')
            ->leftJoin('products', 'transaction_lines.product_id', '=', 'products.id')
            ->select('transaction_lines.*', 'products.name as product_name')
            ->where('transaction_id', $transactionId)
            ->get();

        $data = [
            'transaction' => $transaction,
            'items' => $items,
        ];

        // Ensure you have a view at resources/views/pdf/invoice.blade.php
        $pdf = Pdf::loadView('pdf.invoice', $data);
        return $pdf->output();
    }
}
