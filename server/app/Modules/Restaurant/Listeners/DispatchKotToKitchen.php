<?php

namespace App\Modules\Restaurant\Listeners;

use App\Domain\Shared\Events\KotTicketEmitted;
use App\Modules\Restaurant\Jobs\PrintKitchenThermalTicketJob;
use Illuminate\Support\Facades\Log;

class DispatchKotToKitchen
{
    public function handle(KotTicketEmitted $event): void
    {
        $payload = $event->getPayload();

        // 1. Hyper-lightweight KDS WebSocket push (Pusher/Soketi channel)
        // In production: Broadcast event on channel kitchen.{business_id}
        // broadcast(new \App\Events\KdsTicketBroadcast($payload))->toOthers();

        Log::info("KDS Broadcast simulated for Business #{$payload['business_id']}", [
            'ticket' => $payload['ticket_number'],
            'items'  => $payload['items'],
        ]);

        // 2. Queue async thermal print job on Redis kitchen_print channel
        dispatch(new PrintKitchenThermalTicketJob(
            $payload['business_id'],
            $payload['ticket_number'],
            $payload['items']
        ));
    }
}
