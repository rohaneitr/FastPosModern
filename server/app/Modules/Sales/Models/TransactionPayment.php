<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;

/**
 * TransactionPayment Model
 *
 * Represents a payment record tied to a transaction.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $transaction_id
 * @property float  $amount
 * @property string $method   ('cash', 'card', 'bank_transfer')
 * @property string|null $payment_ref_no
 * @property \Carbon\Carbon $paid_on
 * @property int    $created_by
 */
class TransactionPayment extends Model
{
    use BelongsToBusiness;

    protected $table = 'transaction_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'amount'   => 'decimal:4',
        'paid_on'  => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\IAM\Models\User::class, 'created_by');
    }
}
