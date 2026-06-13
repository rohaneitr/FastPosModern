<?php

namespace App\Modules\Sales\Models;

use App\Modules\Core\Traits\Auditable;
use App\Modules\Tenant\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sale (Transaction) Model
 *
 * The core sales/POS transaction record.
 * Table: transactions
 * Tenant-isolated via business_id (direct column, Phase 1 pre-existing).
 */
class Sale extends Model
{
    use BelongsToBusiness, Auditable, SoftDeletes;

    protected $table = 'transactions';
    protected $guarded = ['id'];

    // Fields to completely omit from audit logs (not masked, fully excluded)
    protected array $auditExclude = ['idempotency_key'];

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The line items (products) within this transaction.
     * Phase 2: TransactionLine now has its own business_id.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class, 'transaction_id');
    }

    /**
     * Payment records for this transaction.
     * Phase 2: TransactionPayment now has its own business_id.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class, 'transaction_id');
    }
}
