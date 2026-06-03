<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Tenant\Models\TenantModel;
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
