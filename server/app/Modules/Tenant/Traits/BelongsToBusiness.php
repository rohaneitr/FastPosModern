<?php

namespace App\Modules\Tenant\Traits;

use App\Modules\Tenant\Scopes\BusinessScope;

trait BelongsToBusiness
{
    /**
     * Boot the BelongsToBusiness trait for a model.
     *
     * @return void
     */
    protected static function bootBelongsToBusiness()
    {
        static::addGlobalScope(new BusinessScope());

        static::creating(function ($model) {
            if (auth()->hasUser() && auth()->user()->business_id && empty($model->business_id)) {
                $model->business_id = auth()->user()->business_id;
            }
        });
    }

    /**
     * Business relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function business()
    {
        return $this->belongsTo(\App\Models\Business::class, 'business_id');
    }
}
