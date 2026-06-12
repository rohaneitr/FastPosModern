<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;

/**
 * StockAdjustment Model
 *
 * Records a manual inventory stock adjustment made by a user.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $product_id
 * @property int    $location_id
 * @property int    $adjusted_by
 * @property float  $quantity      (positive = addition, negative = reduction)
 * @property float  $qty_before
 * @property float  $qty_after
 * @property string|null $reason
 */
class StockAdjustment extends Model
{
    use BelongsToBusiness;

    protected $table = 'stock_adjustments';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'qty_before' => 'decimal:4',
        'qty_after'  => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Tenant\Models\Location::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\IAM\Models\User::class, 'adjusted_by');
    }
}
