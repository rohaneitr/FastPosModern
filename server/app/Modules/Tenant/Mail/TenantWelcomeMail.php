<?php

namespace App\Modules\Tenant\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $businessName;
    public string $ownerEmail;
    public string $temporaryPassword;
    public string $planName;
    public ?string $licenseKey;
    public string $loginUrl;

    public function __construct(
        string $businessName,
        string $ownerEmail,
        string $temporaryPassword,
        string $planName,
        ?string $licenseKey
    ) {
        $this->businessName = $businessName;
        $this->ownerEmail = $ownerEmail;
        $this->temporaryPassword = $temporaryPassword;
        $this->planName = $planName;
        $this->licenseKey = $licenseKey;
        
        // Use the tenant-specific login URL or fallback to the master frontend domain
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $this->loginUrl = rtrim($frontendUrl, '/') . '/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to FastPOS - {$this->businessName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.welcome',
        );
    }
}
