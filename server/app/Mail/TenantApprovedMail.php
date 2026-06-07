<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a SuperAdmin approves a tenant onboarding request.
 * Contains login credentials and (for hybrid/mobile plans) the ECDSA license key.
 */
class TenantApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $businessName;
    public string $ownerEmail;
    public string $temporaryPassword;
    public ?string $licenseKey;
    public string $planName;
    public string $loginUrl;

    public function __construct(
        string  $businessName,
        string  $ownerEmail,
        string  $temporaryPassword,
        string  $planName,
        ?string $licenseKey = null,
    ) {
        $this->businessName      = $businessName;
        $this->ownerEmail        = $ownerEmail;
        $this->temporaryPassword = $temporaryPassword;
        $this->planName          = $planName;
        $this->licenseKey        = $licenseKey;
        $this->loginUrl          = config('app.frontend_url', 'http://localhost:3000') . '/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Welcome to FastPOS — Your Account is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-approved',
        );
    }
}
