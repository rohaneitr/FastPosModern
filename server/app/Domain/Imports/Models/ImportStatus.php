<?php

namespace App\Domain\Imports\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Models\Business;

class ImportStatus extends Model
{
    protected $table = 'import_statuses';

    protected $fillable = [
        'business_id',
        'type',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'errors'
    ];

    protected $casts = [
        'errors' => 'array'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
