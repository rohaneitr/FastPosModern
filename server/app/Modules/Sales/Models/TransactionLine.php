<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;
use App\Modules\Inventory\Models\Product;
use App\Modules\Catalog\Models\Variation;

/**
 * TransactionLine Model
 *
 * Represents a single line item within a sales transaction.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $transaction_id
 * @property int    $product_id
 * @property int|null $variation_id
 * @property float  $quantity
 * @property float  $unit_price_before_discount
 * @property float  $unit_price
 * @property float  $unit_price_inc_tax
 * @property float  $item_tax
 * @property float  $tax_rate
 * @property float  $tax_amount
 * @property string|null $warranty_duration
 * @property int|null $prescription_id
 * @property string|null $dosage_instructions
 * @property string $sourcing_status
 */
class TransactionLine extends Model
{
    use BelongsToBusiness;

    protected $table = 'transaction_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity'                   => 'decimal:4',
        'unit_price_before_discount' => 'decimal:4',
        'unit_price'                 => 'decimal:4',
        'unit_price_inc_tax'         => 'decimal:4',
        'item_tax'                   => 'decimal:4',
        'tax_rate'                   => 'decimal:4',
        'tax_amount'                 => 'decimal:4',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }
}
