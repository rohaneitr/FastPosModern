<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Variation extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    // Note: Variation relies on Product for its tenant scope, so it doesn't strictly need to extend TenantModel, 
    // but queries must be joined through Product if queried directly.
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
