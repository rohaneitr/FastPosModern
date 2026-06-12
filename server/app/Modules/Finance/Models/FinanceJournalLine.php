<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Tenant\Traits\BelongsToBusiness;

/**
 * FinanceJournalLine Model
 *
 * Represents a single debit or credit line within a finance journal entry.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $journal_entry_id
 * @property int    $account_id
 * @property string $type    ('debit' | 'credit')
 * @property float  $amount
 */
class FinanceJournalLine extends Model
{
    use BelongsToBusiness;

    protected $table = 'finance_journal_lines';

    protected $fillable = [
        'business_id',
        'journal_entry_id',
        'account_id',
        'type',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(FinanceJournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }
}
