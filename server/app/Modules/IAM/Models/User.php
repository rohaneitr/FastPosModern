<?php

namespace App\Modules\IAM\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Tenant\Models\Business;
use Laravel\Sanctum\HasApiTokens;
use App\Modules\Core\Traits\Auditable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, Auditable;

    protected $guard_name = 'sanctum';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'surname',
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'language',
        'user_type',
        'allow_login',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
            'allow_login' => 'boolean',
        ];
    }

    /**
     * Append virtual attributes to JSON.
     */
    protected $appends = ['name'];

    // Auditable trait handles all activity logging with PII masking built-in.
    // two_factor_secret and two_factor_recovery_codes are in Activity::MASKED_FIELDS.
    // remember_token changes are suppressed via logOnlyDirty + MASKED_FIELDS exclusion.

    /**
     * Get a display name from first_name + last_name.
     */
    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * The business this user belongs to.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
