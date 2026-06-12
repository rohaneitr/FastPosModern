<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Modules\Tenant\Models\Business;

class TrialExpired extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $business;

    /**
     * Create a new message instance.
     */
    public function __construct(Business $business)
    {
        $this->business = $business;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your FastPOS trial has expired',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "<p>Hello,</p><p>Your trial for <strong>{$this->business->name}</strong> has expired. Your account has been suspended. Please login and upgrade your subscription to reactivate your account.</p>"
        );
    }
}
