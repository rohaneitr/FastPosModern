<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends TenantModel
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'enable_stock' => 'boolean',
        'attributes' => 'array',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variations()
    {
        return $this->hasMany(Variation::class);
    }
}
