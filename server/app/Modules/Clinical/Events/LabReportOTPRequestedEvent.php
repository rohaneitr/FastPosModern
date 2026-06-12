<?php

namespace App\Modules\Clinical\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LabReportOTPRequestedEvent
{
    use Dispatchable, SerializesModels;

    public string $mobileNumber;
    public string $otp;

    /**
     * Create a new event instance.
     */
    public function __construct(string $mobileNumber, string $otp)
    {
        $this->mobileNumber = $mobileNumber;
        $this->otp = $otp;
    }
}
