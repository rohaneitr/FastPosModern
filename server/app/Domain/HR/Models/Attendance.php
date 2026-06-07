<?php

namespace App\Domain\HR\Models;

use App\Domain\Tenant\Models\TenantModel;
use App\Domain\IAM\Models\User;

class Attendance extends TenantModel
{
    protected $table = 'attendances';
    
    protected $fillable = [
        'business_id', 'user_id', 'date', 'clock_in', 'clock_out', 'status'
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
