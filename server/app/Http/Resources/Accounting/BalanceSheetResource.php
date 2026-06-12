<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class BalanceSheetResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'totals' => $this['totals'],
            'breakdown' => $this['breakdown'],
        ];
    }
}
