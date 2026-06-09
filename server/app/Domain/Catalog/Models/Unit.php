<?php

namespace App\Domain\Catalog\Models;

use App\Modules\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends TenantModel
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'allow_decimal' => 'boolean',
    ];
}
