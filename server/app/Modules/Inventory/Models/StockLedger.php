<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\BelongsToBusiness;

class StockLedger extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function delete()
    {
        throw new \Exception('Stock movements are immutable and cannot be deleted.');
    }

    public function forceDelete()
    {
        throw new \Exception('Stock movements are immutable and cannot be deleted.');
    }
}
