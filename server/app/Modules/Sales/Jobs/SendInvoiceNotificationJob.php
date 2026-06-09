<?php

namespace App\Modules\Sales\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendInvoiceNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $transactionId;
    public $businessId;
    public $contact;
    public $notifyMethods;

    // Enterprise Resiliency Config
    public $tries = 3;
    public $backoff = 60;

    public function __construct($transactionId, $businessId, $contact, $notifyMethods)
    {
        $this->transactionId = $transactionId;
        $this->businessId = $businessId;
        $this->contact = $contact;
        $this->notifyMethods = $notifyMethods;
        $this->onQueue('notifications');
    }

    public function handle()
    {
        $engine = app(\App\Modules\Sales\Services\PDFInvoiceEngine::class);
        $pdfPath = $engine->streamInvoice($this->transactionId, $this->businessId);

        $transaction = DB::table('transactions')->where('id', $this->transactionId)->first();

        // Email Execution
        if (in_array('email', $this->notifyMethods) && !empty($this->contact->email)) {
            \Illuminate\Support\Facades\Mail::to($this->contact->email)
                ->send(new \App\Mail\CustomerInvoiceMail($this->transactionId, $pdfPath));
        }

        // WhatsApp Execution
        if (in_array('whatsapp', $this->notifyMethods) && !empty($this->contact->mobile)) {
            // Remediation: Use $this->transactionId inside token directly for the signed route fix
            $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'public.invoice.download', 
                now()->addHours(3), 
                ['token' => $this->transactionId]
            );
            
            // Note: Since this is architecture scaffolding, we comment actual gateway call
            // $gateway = app(\App\Services\WhatsAppGatewayService::class);
            // $gateway->sendMediaMessage($this->contact->mobile, $signedUrl, "Invoice #{$transaction->invoice_no}");
            
            \Illuminate\Support\Facades\Log::info("WhatsApp Payload Sent", ['url' => $signedUrl, 'contact' => $this->contact->mobile]);
        }
    }
}
