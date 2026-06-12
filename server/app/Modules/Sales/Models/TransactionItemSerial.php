<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;

/**
 * TransactionItemSerial Model
 *
 * Records serial numbers / IMEI numbers sold within a transaction line.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * Previously: 3-level join needed: transaction_item_serials → transaction_lines → transactions → business_id
 * Now: Direct business_id column with FK + index.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $transaction_item_id
 * @property string $serial_number
 * @property string|null $imei_number
 */
class TransactionItemSerial extends Model
{
    use BelongsToBusiness;

    protected $table = 'transaction_item_serials';

    protected $guarded = ['id'];

    public function transactionLine(): BelongsTo
    {
        return $this->belongsTo(TransactionLine::class, 'transaction_item_id');
    }
}
