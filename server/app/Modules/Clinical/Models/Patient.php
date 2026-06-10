<?php

namespace App\Modules\Clinical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToBusiness; // Tenant Global Scope

class Patient extends Model
{
    use SoftDeletes, BelongsToBusiness;

    protected $table = 'clinical_patients';

    protected $fillable = [
        'business_id',
        'patient_uid',
        'first_name',
        'last_name',
        'mobile_number',
        'date_of_birth',
        'address',
        'gender',
        'blood_group',
    ];

    /**
     * Data Privacy & Compliance (HIPAA/GDPR)
     * These PII fields are encrypted before saving to the DB and decrypted when accessed.
     */
    protected $casts = [
        'first_name' => 'encrypted',
        'last_name' => 'encrypted',
        'mobile_number' => 'encrypted',
        'date_of_birth' => 'encrypted',
        'address' => 'encrypted',
    ];
}
