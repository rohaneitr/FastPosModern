<?php

namespace App\Modules\Restaurant\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderSentToKitchenEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket;
    public $businessId;

    /**
     * Create a new event instance.
     */
    public function __construct(array $ticket, int $businessId)
    {
        $this->ticket = $ticket;
        $this->businessId = $businessId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // SECURITY PARITY: Only authenticated users with the module.restaurant 
        // entitlement can listen to this private business channel.
        return [
            new PrivateChannel('business.' . $this->businessId . '.kds'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'OrderSentToKitchenEvent';
    }
}
