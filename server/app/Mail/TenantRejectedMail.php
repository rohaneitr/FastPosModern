<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a SuperAdmin rejects a tenant onboarding request.
 * Includes the specific rejection reason so the applicant can re-apply correctly.
 */
class TenantRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $businessName;
    public string $rejectionReason;
    public string $supportUrl;

    public function __construct(string $businessName, string $rejectionReason)
    {
        $this->businessName    = $businessName;
        $this->rejectionReason = $rejectionReason;
        $this->supportUrl      = config('app.frontend_url', 'http://localhost:3000') . '/contact';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on Your FastPOS Application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-rejected',
        );
    }
}
