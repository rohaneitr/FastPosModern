<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends TenantModel
{
    use SoftDeletes, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'enable_stock' => 'boolean',
        'attributes' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logOnlyDirty();
    }

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
