<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Domain\Sales\Services\InvoicePdfService;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $transactionId;
    protected $recipientEmail;

    public function __construct($transactionId, $recipientEmail)
    {
        $this->transactionId = $transactionId;
        $this->recipientEmail = $recipientEmail;
    }

    public function handle(InvoicePdfService $pdfService): void
    {
        $transaction = DB::table('transactions')
            ->join('businesses', 'transactions.business_id', '=', 'businesses.id')
            ->select('transactions.*', 'businesses.name as business_name', 'businesses.communication_settings')
            ->where('transactions.id', $this->transactionId)
            ->first();

        if (!$transaction) {
            return;
        }

        // Dynamically configure SMTP from Tenant Settings
        $commSettings = $transaction->communication_settings ? json_decode($transaction->communication_settings, true) : [];
        
        if (!empty($commSettings['smtp_host'])) {
            Config::set('mail.mailers.smtp.host', $commSettings['smtp_host']);
            Config::set('mail.mailers.smtp.port', $commSettings['smtp_port']);
            Config::set('mail.mailers.smtp.encryption', $commSettings['smtp_encryption']);
            Config::set('mail.mailers.smtp.username', $commSettings['smtp_username']);
            Config::set('mail.mailers.smtp.password', $commSettings['smtp_password']);
            Config::set('mail.from.address', $commSettings['smtp_from_address'] ?? 'noreply@fastpos.com');
            Config::set('mail.from.name', $transaction->business_name);
        }

        // Generate PDF
        $pdfContent = $pdfService->generateInvoicePdf($this->transactionId);
        $fileName = 'Invoice_' . $transaction->invoice_no . '.pdf';

        // Send Email
        Mail::raw("Dear Customer,\n\nPlease find attached your invoice {$transaction->invoice_no} from {$transaction->business_name}.\n\nThank you for your business!", function ($message) use ($transaction, $pdfContent, $fileName) {
            $message->to($this->recipientEmail)
                    ->subject("Invoice {$transaction->invoice_no} from {$transaction->business_name}")
                    ->attachData($pdfContent, $fileName, [
                        'mime' => 'application/pdf',
                    ]);
        });
    }
}
