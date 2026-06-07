<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Services\SmsGatewayService;

class InvoiceGenerated extends Notification implements ShouldQueue
{
    use Queueable;

    public $transaction;
    public $storeName;

    /**
     * Create a new notification instance.
     */
    public function __construct($transaction, $storeName)
    {
        $this->transaction = $transaction;
        $this->storeName = $storeName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class]; // We will use a custom SMS channel
    }

    public function toSms(object $notifiable): array
    {
        // Public receipt link
        $link = config('app.frontend_url') . '/receipt/' . $this->transaction->invoice_no;

        return [
            'phone' => $notifiable->mobile ?? $notifiable->phone_number ?? '01700000000',
            'message' => "Thank you for purchasing from {$this->storeName}! Your total is {$this->transaction->final_total}. View your digital receipt here: {$link}"
        ];
    }
}
