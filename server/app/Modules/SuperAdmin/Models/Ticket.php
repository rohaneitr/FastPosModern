<?php

namespace App\Modules\SuperAdmin\Models;

use App\Modules\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends TenantModel
{
    protected $fillable = [
        'business_id',
        'user_id',
        'subject',
        'status',
        'priority',
    ];

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Tenant\Models\Business::class, 'business_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
