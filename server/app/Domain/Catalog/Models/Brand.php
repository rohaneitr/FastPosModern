<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends TenantModel
{
    use SoftDeletes;

    protected $guarded = ['id'];
}
