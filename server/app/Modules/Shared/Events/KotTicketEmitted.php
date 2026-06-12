<?php

namespace App\Modules\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KotTicketEmitted implements ModuleEventInterface
{
    use Dispatchable, SerializesModels;

    public int $businessId;
    public int $sessionId;
    public string $ticketNumber;
    public array $items;

    public function __construct(int $businessId, int $sessionId, string $ticketNumber, array $items)
    {
        $this->businessId   = $businessId;
        $this->sessionId    = $sessionId;
        $this->ticketNumber = $ticketNumber;
        $this->items        = $items;
    }

    public function getEventName(): string
    {
        return 'KotTicketEmitted';
    }

    public function getPayload(): array
    {
        return [
            'business_id'   => $this->businessId,
            'session_id'    => $this->sessionId,
            'ticket_number' => $this->ticketNumber,
            'items'         => $this->items,
        ];
    }
}
