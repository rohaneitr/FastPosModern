<?php

namespace App\Http\Controllers\Api\V1\POS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicInvoiceController extends Controller
{
    public function download(Request $request, $token)
    {
        // $token here is the transaction ID because we mapped ['token' => $transaction->id]
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        $transaction = DB::table('transactions')->where('id', $token)->first();

        if (!$transaction) {
            abort(404, 'Transaction not found.');
        }

        $disk = Storage::disk('local');
        $fileName = "secure_invoices/invoice_{$transaction->invoice_no}.pdf";

        if (!$disk->exists($fileName)) {
            // Generate on the fly if somehow missing
            $engine = app(\App\Domain\POS\Services\PDFInvoiceEngine::class);
            $engine->streamInvoice($transaction->id, $transaction->business_id);
        }

        if (!$disk->exists($fileName)) {
            abort(404, 'Invoice PDF could not be generated.');
        }

        return response()->file($disk->path($fileName), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
