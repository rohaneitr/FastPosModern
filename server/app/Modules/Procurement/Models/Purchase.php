<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Purchase extends Model
{
    use BelongsToBusiness, LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logOnlyDirty();
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseLine::class);
    }
}
