<?php

namespace App\Domain\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCompleted implements ModuleEventInterface
{
    use Dispatchable, SerializesModels;

    public $businessId;
    public $transactionId;
    public $lines;

    public function __construct(int $businessId, int $transactionId, array $lines)
    {
        $this->businessId = $businessId;
        $this->transactionId = $transactionId;
        $this->lines = $lines;
    }

    public function getEventName(): string
    {
        return 'TransactionCompleted';
    }

    public function getPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'transaction_id' => $this->transactionId,
            'lines' => $this->lines
        ];
    }
}
