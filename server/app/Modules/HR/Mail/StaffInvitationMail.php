<?php

namespace App\Modules\HR\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Modules\HR\Models\TeamInvitation;

class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;
    public $businessName;

    public function __construct(TeamInvitation $invitation, string $businessName)
    {
        $this->invitation = $invitation;
        $this->businessName = $businessName;
    }

    public function build()
    {
        // Using config app.frontend_url to build the registration link
        $registrationUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/') . '/register?invitation=' . $this->invitation->token;

        return $this->subject("You've been invited to join {$this->businessName} on FastPOS")
                    ->view('emails.hr.staff-invitation')
                    ->with([
                        'role' => $this->invitation->role,
                        'registrationUrl' => $registrationUrl,
                        'businessName' => $this->businessName,
                    ]);
    }
}
