<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use App\Modules\SuperAdmin\Models\EmailLog;
use Illuminate\Support\Facades\Log;

class LogSentEmail
{
    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $data = $event->data;

            $toAddresses = $message->getTo() ? array_map(function($addr) { return $addr->getAddress(); }, $message->getTo()) : [];
            $toEmail = !empty($toAddresses) ? implode(', ', $toAddresses) : 'unknown';

            $subject = $message->getSubject() ?? 'No Subject';

            // Extract the mailable class name if available
            $mailableClass = isset($data['__laravel_mailable']) ? get_class($data['__laravel_mailable']) : null;

            EmailLog::create([
                'business_id' => tenant('id') ?? null, // Uses tenant helper if available
                'to_email' => $toEmail,
                'subject' => $subject,
                'mailable_class' => $mailableClass,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log sent email: ' . $e->getMessage());
        }
    }
}
