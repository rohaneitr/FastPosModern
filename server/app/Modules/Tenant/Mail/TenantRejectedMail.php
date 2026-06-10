<?php

namespace App\Modules\Tenant\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $businessName;
    public string $rejectionReason;

    public function __construct(string $businessName, string $rejectionReason)
    {
        $this->businessName = $businessName;
        $this->rejectionReason = $rejectionReason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Update regarding your FastPOS Registration: {$this->businessName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.rejected',
        );
    }
}
