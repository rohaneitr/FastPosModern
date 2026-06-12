<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Modules\Tenant\Models\Business;

class UpcomingTrialExpiry extends Mailable implements ShouldQueue
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
            subject: 'Your FastPOS trial is expiring soon!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "<p>Hello,</p><p>Your trial for <strong>{$this->business->name}</strong> will expire in 3 days. Please add a payment method or purchase a subscription to continue using our services.</p>"
        );
    }
}
