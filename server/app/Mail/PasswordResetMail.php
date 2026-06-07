<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    /**
     * @param string $token   The raw 64-char token (NOT the bcrypt hash)
     * @param string $resetUrl Pre-built URL: /reset-password?token=...&email=...
     */
    public function __construct(string $token, string $resetUrl)
    {
        // We do NOT store the raw token as a public property to avoid
        // it being accidentally serialised into logs or job payloads.
        $this->resetUrl = $resetUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your FastPOS Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-reset',
        );
    }
}
