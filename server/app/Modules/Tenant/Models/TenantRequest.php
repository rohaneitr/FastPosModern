<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;

class TenantRequest extends Model
{
    protected $table = 'tenant_requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'business_name',
        'applicant_name',
        'applicant_email',
        'plan_id',
        'status',
        'rejection_reason',
        'tenant_id',
        'reviewed_by',
        'reviewed_at',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Modules\IAM\Models\User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
