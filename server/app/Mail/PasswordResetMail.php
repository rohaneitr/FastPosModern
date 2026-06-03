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

    public string $resetToken;
    public string $resetUrl;

    public function __construct(string $token)
    {
        $this->resetToken = $token;
        $this->resetUrl = config('app.frontend_url', 'http://localhost:3000') . '/login?reset_token=' . $token;
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
