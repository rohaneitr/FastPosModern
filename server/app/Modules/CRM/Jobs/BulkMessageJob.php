<?php

namespace App\Modules\CRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BulkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $targets;
    public $subject;
    public $messageBody;
    public $businessName;

    public function __construct(array $targets, string $subject, string $messageBody, string $businessName)
    {
        $this->targets = $targets;
        $this->subject = $subject;
        $this->messageBody = $messageBody;
        $this->businessName = $businessName;
    }

    public function handle(): void
    {
        foreach ($this->targets as $target) {
            try {
                // Here we would use a dedicated Mailable, using a raw mail for simplicity/speed in this job
                Mail::raw($this->messageBody, function ($message) use ($target) {
                    $message->to($target['email'])
                            ->subject($this->subject);
                });
            } catch (\Exception $e) {
                Log::error("Failed to send bulk message to {$target['email']}: " . $e->getMessage());
                // Continue with other targets instead of failing the whole job
            }
        }
    }
}
