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
}
