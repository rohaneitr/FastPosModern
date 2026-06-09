<?php

namespace App\Domain\Catalog\Models;

use App\Modules\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends TenantModel
{
    use SoftDeletes;

    protected $guarded = ['id'];
}
