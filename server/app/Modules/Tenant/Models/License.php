<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'license_key',
        'status',
        'device_limit',
        'employee_limit',
        'activated_at',
        'expires_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Business::class, 'tenant_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function deviceActivations()
    {
        return $this->hasMany(DeviceActivation::class, 'license_key', 'license_key');
    }
}
