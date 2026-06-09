<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TenantModel
 * 
 * Base model for all multi-tenant aware domains. 
 * Automatically scopes queries to the current authenticated user's business_id.
 */
abstract class TenantModel extends Model
{
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Check if there is an authenticated user with a business_id
            if (auth()->hasUser() && auth()->user()->business_id) {
                $builder->where('business_id', auth()->user()->business_id);
            }
        });

        static::creating(function ($model) {
            if (auth()->hasUser() && auth()->user()->business_id && empty($model->business_id)) {
                $model->business_id = auth()->user()->business_id;
            }
        });
    }

    /**
     * Remove the tenant scope for cross-tenant or admin queries.
     */
    public static function withoutTenantScope()
    {
        return static::withoutGlobalScope('tenant');
    }
}
