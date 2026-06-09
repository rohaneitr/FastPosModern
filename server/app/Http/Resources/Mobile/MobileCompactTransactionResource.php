<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class MobileCompactTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Extremely lightweight transaction mapping.
     */
    public function toArray($request)
    {
        return [
            'id'  => $this->id,
            'inv' => $this->invoice_no,
            'tot' => (float) $this->final_total,
            'sts' => substr($this->payment_status, 0, 1), // e.g., 'p' for paid, 'd' for due
            'dt'  => strtotime($this->transaction_date) // Return epoch timestamp integer
        ];
    }
}
