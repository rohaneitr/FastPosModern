<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCompletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $businessId;
    public int $importStatusId;
    public string $finalStatus;

    public function __construct(int $businessId, int $importStatusId, string $finalStatus)
    {
        $this->businessId = $businessId;
        $this->importStatusId = $importStatusId;
        $this->finalStatus = $finalStatus;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('business.' . $this->businessId);
    }

    public function broadcastAs()
    {
        return 'import.completed';
    }

    public function broadcastWith()
    {
        return [
            'import_id' => $this->importStatusId,
            'status' => $this->finalStatus,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
