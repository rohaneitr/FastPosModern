<?php

namespace App\Modules\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Modules\Tenant\Services\TenantContext;

class BusinessScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Resolution order:
     *   1. Authenticated HTTP user (auth()->user()->business_id) — for all HTTP requests
     *   2. TenantContext::get() — for queued jobs and Artisan commands
     *   3. Explicit SuperAdmin check — Bypasses scope for cross-tenant operations
     *   4. FAIL CLOSED — If none of the above match, the query is aggressively clamped.
     */
    public function apply(Builder $builder, Model $model)
    {
        if (auth()->hasUser() && auth()->user()->business_id) {
            // HTTP request path — always wins
            $builder->where($model->getTable() . '.business_id', auth()->user()->business_id);
        } elseif (TenantContext::isActive()) {
            // Background job / Artisan path — explicit tenant context
            $builder->where($model->getTable() . '.business_id', TenantContext::get());
        } else {
            // Explicit SuperAdmin check
            if (auth()->hasUser() && auth()->user()->hasRole('SuperAdmin')) {
                // No scope applied — intentional for explicitly verified SuperAdmin
                return;
            }

            // FAIL CLOSED pattern: Aggressively clamp the query to prevent data leakage
            $builder->whereRaw('1 = 0');
        }
    }
}

