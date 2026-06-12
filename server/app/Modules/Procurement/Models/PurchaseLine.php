<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;
use App\Modules\Inventory\Models\Product;

/**
 * PurchaseLine Model
 *
 * Represents a single line item within a purchase order.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * PREVIOUSLY: Relied on Purchase (parent) to provide tenant scope via JOIN.
 * NOW: Has its own direct business_id column and BelongsToBusiness trait.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $purchase_id
 * @property int    $product_id
 * @property float  $quantity
 * @property float  $purchase_price
 * @property float  $sub_total
 * @property float  $item_tax
 */
class PurchaseLine extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity'       => 'decimal:4',
        'purchase_price' => 'decimal:4',
        'sub_total'      => 'decimal:4',
        'item_tax'       => 'decimal:4',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
