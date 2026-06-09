<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenant\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Sale extends Model
{
    use BelongsToBusiness, LogsActivity, SoftDeletes;

    protected $table = 'transactions'; // Assuming Sales are stored in the transactions table
    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logOnlyDirty();
    }
}
