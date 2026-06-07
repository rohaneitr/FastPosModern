<?php

namespace App\Listeners;

use App\Domain\Tenant\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

/**
 * Listens for Laravel's MessageSent event and writes an immutable
 * record to email_logs. Registered in EventServiceProvider.
 *
 * The MessageSent event fires AFTER the message is handed to the
 * transport (SMTP), so status = 'sent' is accurate here.
 */
class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;

            // Extract first To address
            $toAddresses = $message->getTo();
            $toEmail     = $toAddresses ? array_key_first($toAddresses) : 'unknown';
            $subject     = $message->getSubject() ?? '(no subject)';

            // Try to identify the originating Mailable from the message data bag
            $mailableClass = null;
            $data = $event->data ?? [];
            if (isset($data['__laravel_mailable'])) {
                $mailableClass = get_class($data['__laravel_mailable']);
            }

            EmailLog::create([
                'to_email'       => $toEmail,
                'subject'        => $subject,
                'status'         => 'sent',
                'mailable_class' => $mailableClass,
                'sent_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('LogSentEmail listener failed', ['error' => $e->getMessage()]);
        }
    }
}
