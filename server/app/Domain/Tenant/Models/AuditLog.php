<?php

namespace App\Domain\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\IAM\Models\User;

/**
 * AuditLog — immutable activity record.
 *
 * Never update rows in this table. Only create them.
 * Use AuditLogger::record() for a clean API.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // immutable — no updated_at

    protected $fillable = [
        'causer_id',
        'causer_type',
        'causer_name',
        'event',
        'description',
        'properties',
        'subject_type',
        'subject_id',
        'subject_label',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
