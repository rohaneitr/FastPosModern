<?php

namespace App\Domain\Tenant\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected static function newFactory()
    {
        return \Database\Factories\BusinessFactory::new();
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date'             => 'date',
        'settings'               => 'array',
        'enabled_modules'        => 'array',
        'active_modules'         => 'array',
        'communication_settings' => 'array',
        'is_active'              => 'boolean',
        'subscription_expires_at'=> 'datetime',
    ];

    /**
     * Check if a specific module is enabled for this tenant.
     * Defaults to TRUE when enabled_modules is null (no restrictions set).
     */
    public function hasModule(string $module): bool
    {
        if ($this->enabled_modules === null) {
            return true; // no restrictions — all modules allowed
        }
        return (bool) ($this->enabled_modules[$module] ?? true);
    }

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
