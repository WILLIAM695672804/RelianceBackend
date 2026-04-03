<?php

namespace App\Modules\Profile\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

class TechnicianSkill extends Pivot
{
    protected $table = 'technician_skills';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'skill_id',
        'proficiency',
        'hourly_rate',
        'travel_fee',
        'meta',
    ];

    protected $casts = [
        'proficiency' => 'integer',
        'hourly_rate' => 'decimal:2',
        'travel_fee' => 'decimal:2',
        'meta' => 'json',
    ];

    protected static function booted()
    {
        static::creating(function ($pivot) {
            if (!$pivot->id) {
                $pivot->id = (string) Str::uuid();
            }
        });
    }
}
