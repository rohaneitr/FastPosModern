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
                $user = auth()->user();
                $table = $builder->getModel()->getTable();
                
                $builder->where($table . '.business_id', $user->business_id);

                // Scope Creep Prevention: Hardware/Location Isolation
                // If the model has a location_id and the user is NOT a Manager/Admin
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'location_id')) {
                    if (!$user->hasAnyRole(['Manager', 'BusinessAdmin', 'SuperAdmin']) && $user->location_id) {
                        $builder->where($table . '.location_id', $user->location_id);
                    }
                }
            }
        });

        static::creating(function ($model) {
            if (auth()->hasUser() && auth()->user()->business_id) {
                if (empty($model->business_id)) {
                    $model->business_id = auth()->user()->business_id;
                }
                
                // Assign location automatically if strictly isolated
                $user = auth()->user();
                if (\Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'location_id') && empty($model->location_id)) {
                    if (!$user->hasAnyRole(['Manager', 'BusinessAdmin', 'SuperAdmin']) && $user->location_id) {
                        $model->location_id = $user->location_id;
                    }
                }
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
