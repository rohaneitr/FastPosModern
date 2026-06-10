<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'limit_overrides' => 'array',
        'module_overrides' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive()
    {
        return $this->status === 'active' || 
               ($this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isFuture());
    }

    public function isTrialing()
    {
        return $this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
    
    public function isPastDue()
    {
        return $this->status === 'past_due' || ($this->status === 'active' && $this->current_period_end && $this->current_period_end->isPast());
    }

    public function getResolvedUserLimitAttribute()
    {
        $base = $this->plan ? ($this->plan->user_limit ?? $this->plan->max_users ?? 0) : 0;
        return $base + ($this->limit_overrides['user_limit'] ?? 0);
    }

    public function getResolvedLocationLimitAttribute()
    {
        $base = $this->plan ? ($this->plan->location_limit ?? $this->plan->max_locations ?? 0) : 0;
        return $base + ($this->limit_overrides['location_limit'] ?? 0);
    }

    public function getResolvedDeviceLimitAttribute()
    {
        $base = $this->plan ? ($this->plan->device_limit ?? 0) : 0;
        return $base + ($this->limit_overrides['device_limit'] ?? 0);
    }

    public function getResolvedModulesAttribute()
    {
        $base = $this->plan ? ($this->plan->enabled_modules ?? []) : [];
        if (is_string($base)) {
            $base = json_decode($base, true) ?? [];
        }
        
        $added = $this->module_overrides['added'] ?? [];
        $removed = $this->module_overrides['removed'] ?? [];

        $resolved = array_merge($base, $added);
        return array_values(array_diff(array_unique($resolved), $removed));
    }
}
