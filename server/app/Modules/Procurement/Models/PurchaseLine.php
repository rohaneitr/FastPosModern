<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Inventory\Models\Product;

class PurchaseLine extends Model
{
    protected $guarded = ['id'];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
