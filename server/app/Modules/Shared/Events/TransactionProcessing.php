<?php

namespace App\Modules\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionProcessing implements ModuleEventInterface
{
    use Dispatchable, SerializesModels;

    public $businessId;
    public $lines;

    public function __construct(int $businessId, array $lines)
    {
        $this->businessId = $businessId;
        $this->lines = $lines;
    }

    public function getEventName(): string
    {
        return 'TransactionProcessing';
    }

    public function getPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'lines' => $this->lines
        ];
    }
}
