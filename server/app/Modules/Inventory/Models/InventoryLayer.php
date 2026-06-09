<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLayer extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'purchase_line_id',
        'original_qty',
        'remaining_qty',
        'unit_cost',
    ];

    protected $casts = [
        'original_qty' => 'string',
        'remaining_qty' => 'string',
        'unit_cost' => 'string',
    ];

    public function product()
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\Product::class);
    }
}
