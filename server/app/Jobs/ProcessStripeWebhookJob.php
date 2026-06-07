<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Domain\Tenant\Models\Subscription;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $type;
    public $data;

    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public function handle()
    {
        switch ($this->type) {
            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($this->data);
                break;
            case 'invoice.payment_failed':
                $this->handlePaymentFailed($this->data);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionCancelled($this->data);
                break;
        }
    }

    private function handlePaymentSucceeded($invoice)
    {
        $subId = $invoice['subscription'] ?? null;
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update([
            'status' => 'active',
        ]);

        Log::info("Stripe: Payment succeeded for subscription {$subId} (Async)");
    }

    private function handlePaymentFailed($invoice)
    {
        $subId = $invoice['subscription'] ?? null;
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update([
            'status' => 'past_due',
        ]);

        Log::warning("Stripe: Payment failed for subscription {$subId} (Async)");
    }

    private function handleSubscriptionCancelled($subscription)
    {
        $subId = $subscription['id'] ?? null;
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update([
            'status' => 'cancelled',
        ]);

        Log::info("Stripe: Subscription cancelled {$subId} (Async)");
    }

    public function failed(\Throwable $exception)
    {
        Log::error("ProcessStripeWebhookJob Failed: " . $exception->getMessage());
    }
}
