<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class TrialBalanceResource extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource;
    }
}
