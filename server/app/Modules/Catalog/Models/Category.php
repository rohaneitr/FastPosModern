<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends TenantModel
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function subCategories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
