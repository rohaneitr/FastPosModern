<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;

/**
 * Variation Model
 *
 * Represents a product variant (e.g., size/color combination).
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * PREVIOUSLY: Relied on Product to provide tenant scope via JOIN.
 * NOW: Has its own direct business_id column and BelongsToBusiness trait.
 *
 * @property int        $id
 * @property int        $business_id
 * @property int        $product_id
 * @property string     $name
 * @property string|null $sub_sku
 * @property float|null  $default_purchase_price
 * @property float|null  $sell_price_inc_tax
 */
class Variation extends Model
{
    use BelongsToBusiness, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'default_purchase_price' => 'decimal:4',
        'sell_price_inc_tax'     => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
