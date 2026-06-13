<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;

/**
 * ProductStock Model
 *
 * Tracks the available quantity of a product (optionally a variation) at a specific location.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * @property int        $id
 * @property int        $business_id
 * @property int        $product_id
 * @property int|null   $variation_id
 * @property int        $location_id
 * @property float      $qty_available
 * @property \Carbon\Carbon|null $expiry_date
 * @property string|null $lot_number
 */
class ProductStock extends Model
{
    use BelongsToBusiness, Auditable;

    protected $table = 'product_stocks';

    protected $guarded = ['id'];

    protected $casts = [
        'qty_available' => 'decimal:4',
        'expiry_date'   => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\Variation::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Tenant\Models\Location::class);
    }
}
