<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\BelongsToBusiness;

class Product extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockLedgers()
    {
        return $this->hasMany(StockLedger::class);
    }

    public function calculateCurrentStock()
    {
        return $this->stockLedgers()->sum('quantity');
    }
}
