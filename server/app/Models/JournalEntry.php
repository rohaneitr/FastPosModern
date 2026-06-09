<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'reference_id',
        'reference_type',
        'reference_number',
        'date',
        'narration',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
