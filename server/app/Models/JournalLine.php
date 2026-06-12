<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\BelongsToBusiness;

/**
 * JournalLine Model
 *
 * Represents a single debit or credit line within a journal entry.
 * Tenant-isolated directly via business_id (Phase 2 hardening).
 *
 * PREVIOUSLY: Plain Model — relied on JournalEntry (parent) for tenant scope.
 * NOW: Has direct business_id column and BelongsToBusiness trait.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $journal_entry_id
 * @property int    $chart_of_account_id
 * @property string $type   ('debit' | 'credit')
 * @property float  $amount
 * @property string|null $currency_code
 * @property float|null  $exchange_rate_used
 */
class JournalLine extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'journal_entry_id',
        'chart_of_account_id',
        'type',
        'amount',
        'currency_code',
        'exchange_rate_used',
    ];

    protected $casts = [
        'amount'             => 'decimal:4',
        'exchange_rate_used' => 'decimal:6',
    ];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
