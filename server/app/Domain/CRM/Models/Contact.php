<?php

namespace App\Domain\CRM\Models;

use App\Domain\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends TenantModel
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Scope a query to only include customers.
     */
    public function scopeCustomers($query)
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    /**
     * Scope a query to only include suppliers.
     */
    public function scopeSuppliers($query)
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }
}
