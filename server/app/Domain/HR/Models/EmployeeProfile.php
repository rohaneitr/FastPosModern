<?php

namespace App\Domain\HR\Models;

use App\Domain\Tenant\Models\TenantModel;
use App\Domain\IAM\Models\User;

class EmployeeProfile extends TenantModel
{
    protected $table = 'employee_profiles';
    
    protected $fillable = [
        'business_id', 'user_id', 'base_salary', 'joining_date', 
        'designation', 'nid_number', 'emergency_contact'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
