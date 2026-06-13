<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Tenant\Traits\BelongsToBusiness;

class Product extends Model
{
    use BelongsToBusiness, Auditable, SoftDeletes;

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

    public function tax()
    {
        return $this->belongsTo(\App\Modules\Finance\Models\Tax::class, 'tax_id'); // Assuming tax_id exists or standard Laravel naming
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
