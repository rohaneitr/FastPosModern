<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\BelongsToBusiness;

class Brand extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];
}
