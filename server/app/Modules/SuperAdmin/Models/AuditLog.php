<?php

namespace App\Modules\SuperAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false; // The migration only uses `created_at` via timestamp(), no updated_at

    protected $fillable = [
        'business_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Simulate global tenant scope to be explicitly bypassed by SuperAdmin
    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // If the user is authenticated and is a BusinessAdmin/Cashier (not SuperAdmin)
            if (auth()->hasUser() && auth()->user()->business_id && !auth()->user()->hasRole('SuperAdmin')) {
                $builder->where('business_id', auth()->user()->business_id);
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(\App\Modules\Tenant\Models\Business::class, 'business_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
