<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $messageBody;
    public $subjectText;

    public function __construct($subjectText, $messageBody)
    {
        $this->subjectText = $subjectText;
        $this->messageBody = $messageBody;
    }

    public function build()
    {
        return $this->subject($this->subjectText)
                    ->html($this->messageBody);
    }
}
