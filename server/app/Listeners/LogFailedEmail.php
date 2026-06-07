<?php

namespace App\Listeners;

use App\Domain\Tenant\Models\EmailLog;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Support\Facades\Log;

/**
 * Listens for Laravel's MessageFailed event and writes a 'failed'
 * record to email_logs with the error message for debugging.
 */
class LogFailedEmail
{
    public function handle(MessageFailed $event): void
    {
        try {
            $message = $event->message;

            $toAddresses = $message->getTo();
            $toEmail     = $toAddresses ? array_key_first($toAddresses) : 'unknown';
            $subject     = $message->getSubject() ?? '(no subject)';
            $error       = $event->exception->getMessage();

            EmailLog::create([
                'to_email'      => $toEmail,
                'subject'       => $subject,
                'status'        => 'failed',
                'error_message' => substr($error, 0, 1000), // cap at 1000 chars
                'sent_at'       => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('LogFailedEmail listener failed', ['error' => $e->getMessage()]);
        }
    }
}
