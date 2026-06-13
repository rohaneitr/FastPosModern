<?php

namespace App\Modules\Tenant\Models;

use App\Modules\Tenant\Services\TenantContextCache;
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
        'start_date'             => 'date',
        'settings'               => 'array',
        'enabled_modules'        => 'array',
        'active_modules'         => 'array',
        'communication_settings' => 'array',
        'is_active'              => 'boolean',
        'subscription_expires_at'=> 'datetime',
        'trial_ends_at'          => 'datetime',
    ];

    /**
     * Check if the business trial is still active.
     */
    public function isTrialActive(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the business has an active subscription.
     */
    public function isSubscriptionActive(): bool
    {
        // If there's an active subscription model relation
        if ($this->subscription && $this->subscription->status === 'active') {
            // Further verify the subscription hasn't expired if you have period_end checks
            if ($this->subscription->current_period_end) {
                return \Carbon\Carbon::parse($this->subscription->current_period_end)->isFuture();
            }
            return true;
        }

        // Or fallback to checking business subscription fields (if not using subscription model)
        if ($this->subscription_ends_at) {
            return \Carbon\Carbon::parse($this->subscription_ends_at)->isFuture();
        }

        return false;
    }

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
        return $this->belongsTo(\App\Modules\IAM\Models\User::class, 'owner_id');
    }

    /**
     * Get all users associated with this business.
     */
    public function users()
    {
        return $this->hasMany(\App\Modules\IAM\Models\User::class, 'business_id');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * The "booted" method of the model.
     * Handle cascading soft-deletes for multi-tenant data.
     */
    protected static function booted()
    {
        // ── Cache Invalidation (Phase 9) ───────────────────────────────────────
        // MANDATORY: Clear the Redis tenant context cache whenever the Business
        // model is written. This covers ALL write paths:
        //   - Stripe webhook updates subscription_status
        //   - Trial suspension cron sets is_active = false
        //   - Admin updates business settings
        //   - Subscription plan upgrade via SubscriptionController
        //
        // The 'saved' event fires after BOTH create() and update() operations.
        // The 'deleted' event fires on soft-delete (SoftDeletes trait).
        // Together these two hooks guarantee the cache is NEVER stale.
        static::saved(function (self $business) {
            TenantContextCache::forget($business->id);
        });

        static::deleted(function (self $business) {
            TenantContextCache::forget($business->id);
        });

        // ── Cascading Soft-Delete (pre-existing) ──────────────────────────────
        static::deleting(function ($business) {
            foreach (['products', 'purchases', 'transactions'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at')) {
                    \Illuminate\Support\Facades\DB::table($table)->where('business_id', $business->id)->update(['deleted_at' => now()]);
                }
            }
        });

        static::restoring(function ($business) {
            foreach (['products', 'purchases', 'transactions'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at')) {
                    \Illuminate\Support\Facades\DB::table($table)->where('business_id', $business->id)->update(['deleted_at' => null]);
                }
            }
        });
    }
}
