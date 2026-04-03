<?php

namespace App\Modules\Profile\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

class AgencyMembership extends Pivot
{
    protected $table = 'agency_memberships';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'agency_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($membership) {
            if (!$membership->id) {
                $membership->id = (string) Str::uuid();
            }
        });
    }
}
