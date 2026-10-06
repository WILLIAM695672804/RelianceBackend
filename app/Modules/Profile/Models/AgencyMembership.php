<?php

namespace App\Modules\Profile\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** Membre de l'agence ; la relation est requise par AgencyMemberController. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Agence du rattachement. */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    protected static function booted()
    {
        static::creating(function ($membership) {
            if (!$membership->id) {
                $membership->id = (string) Str::uuid();
            }
        });
    }
}
