<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\BelongsToBusiness;

class Contact extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
