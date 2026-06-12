<?php

namespace App\Modules\Tenant\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;
use Illuminate\Database\Eloquent\Builder;

class Activity extends SpatieActivity
{
    protected $fillable = [
        'business_id',
        'log_name',
        'description',
        'subject_type',
        'event',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
    ];

    /**
     * The "booted" method of the model.
     * Ensure Tenant Isolation.
     */
    protected static function booted()
    {
        parent::booted();

        // Automatically assign business_id when logging activity
        static::creating(function ($activity) {
            if (!$activity->business_id && auth()->check() && auth()->user()->business_id) {
                $activity->business_id = auth()->user()->business_id;
            }
        });

        // Global scope to isolate logs per tenant
        static::addGlobalScope('business_id', function (Builder $builder) {
            if (auth()->check() && auth()->user()->business_id) {
                $builder->where('business_id', auth()->user()->business_id);
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
