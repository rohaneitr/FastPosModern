<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use App\Services\SmsGatewayService;

class SmsChannel
{
    protected $smsService;

    public function __construct(SmsGatewayService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (method_exists($notification, 'toSms')) {
            $data = $notification->toSms($notifiable);
            
            if (!empty($data['phone']) && !empty($data['message'])) {
                $this->smsService->sendSms($data['phone'], $data['message']);
            }
        }
    }
}
