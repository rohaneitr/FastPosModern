<?php

namespace App\Domain\HR\Models;

use App\Domain\Tenant\Models\TenantModel;
use App\Domain\IAM\Models\User;

class Payroll extends TenantModel
{
    protected $table = 'payrolls';
    
    protected $fillable = [
        'business_id', 'user_id', 'reference_no', 'month', 'base_salary', 
        'total_working_days', 'present_days', 'gross_salary', 'bonus_commission', 
        'deductions_fines', 'net_salary', 'payment_status', 'expense_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
