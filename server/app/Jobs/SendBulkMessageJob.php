<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericNotificationMail;

class SendBulkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userIds;
    protected $subject;
    protected $messageBody;

    public function __construct(array $userIds, string $subject, string $messageBody)
    {
        $this->userIds = $userIds;
        $this->subject = $subject;
        $this->messageBody = $messageBody;
    }

    public function handle(): void
    {
        $users = DB::table('users')->whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            if (!empty($user->email)) {
                Mail::to($user->email)->send(new GenericNotificationMail($this->subject, $this->messageBody));
            }
        }
    }
}
