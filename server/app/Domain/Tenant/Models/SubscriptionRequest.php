<?php

namespace App\Domain\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\IAM\Models\User;

class SubscriptionRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }
}
