<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class MobileDashboardOverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Hyper-optimized JSON payload for slow 3G/4G networks.
     * Strict elimination of timestamp strings and complex nested metadata.
     */
    public function toArray($request)
    {
        return [
            'rev' => (float) $this->net_revenue,
            'drw' => (bool) $this->is_drawer_open,
            'stk' => (int) $this->low_stock_count,
            'ts'  => (int) $this->timestamp
        ];
    }
}
