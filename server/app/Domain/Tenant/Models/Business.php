<?php

namespace App\Domain\Tenant\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'settings' => 'array',
        'is_active' => 'boolean',
        'subscription_expires_at' => 'datetime',
    ];

    /**
     * Get the owner of the business.
     */
    public function owner()
    {
        return $this->belongsTo(\App\Domain\IAM\Models\User::class, 'owner_id');
    }

    /**
     * Get all users associated with this business.
     */
    public function users()
    {
        return $this->hasMany(\App\Domain\IAM\Models\User::class, 'business_id');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }
}
