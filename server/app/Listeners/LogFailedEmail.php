<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageFailed;
use App\Modules\SuperAdmin\Models\EmailLog;
use Illuminate\Support\Facades\Log;

class LogFailedEmail
{
    public function handle(MessageFailed $event): void
    {
        try {
            $message = $event->message;
            $data = $event->data;

            $toAddresses = $message->getTo() ? array_map(function($addr) { return $addr->getAddress(); }, $message->getTo()) : [];
            $toEmail = !empty($toAddresses) ? implode(', ', $toAddresses) : 'unknown';

            $subject = $message->getSubject() ?? 'No Subject';

            $mailableClass = isset($data['__laravel_mailable']) ? get_class($data['__laravel_mailable']) : null;

            EmailLog::create([
                'business_id' => tenant('id') ?? null,
                'to_email' => $toEmail,
                'subject' => $subject,
                'mailable_class' => $mailableClass,
                'status' => 'failed',
                'error_message' => substr($event->exception->getMessage(), 0, 500),
                'sent_at' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log failed email: ' . $e->getMessage());
        }
    }
}
