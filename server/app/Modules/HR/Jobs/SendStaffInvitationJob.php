<?php

namespace App\Modules\HR\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\HR\Models\TeamInvitation;
use App\Modules\HR\Mail\StaffInvitationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendStaffInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invitation;
    public $businessName;

    public function __construct(TeamInvitation $invitation, string $businessName)
    {
        $this->invitation = $invitation;
        $this->businessName = $businessName;
    }

    public function handle(): void
    {
        try {
            Mail::to($this->invitation->email)->send(new StaffInvitationMail($this->invitation, $this->businessName));
        } catch (\Exception $e) {
            Log::error("Failed to send staff invitation email: " . $e->getMessage(), [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email
            ]);
            
            // Re-throw if you want the job to be marked as failed and retry
            throw $e;
        }
    }
}
