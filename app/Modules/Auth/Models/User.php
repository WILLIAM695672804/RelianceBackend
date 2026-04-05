<?php

namespace App\Modules\Auth\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Kyc\Models\KycDocument;
// use OwenIt\Auditing\Contracts\Auditable as AuditableContract; // Désactivé temporairement
// use OwenIt\Auditing\Auditable; // Désactivé temporairement

/**
 * @method bool hasVerifiedEmail()
 * @method void sendEmailVerificationNotification()
 */

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable, SoftDeletes, HasRoles;

    protected $table = 'users';
    protected $guard_name = 'api';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'avatar_url',
        'rating',
        'num_reviews',
        'verified',
        'last_login',
        'meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'verified' => 'boolean',
        'last_login' => 'datetime',
        'rating' => 'decimal:2',
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });

        static::created(function ($user) {
            \App\Modules\Finance\Models\Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'currency' => 'XAF'
            ]);
        });
    }
    public function kycDocuments()
    {
        return $this->hasMany(KycDocument::class);
    }
    public function hasApprovedKyc(): bool
    {
        return $this->kycDocuments()
            ->where('status', 'approved')
            ->exists();
    }

    public function agencyMemberships()
    {
        return $this->hasMany(\App\Modules\Profile\Models\AgencyMembership::class);
    }

    public function wallet()
    {
        return $this->hasOne(\App\Modules\Finance\Models\Wallet::class);
    }

    public function skills()
    {
        return $this->belongsToMany(\App\Modules\Profile\Models\Skill::class, 'technician_skills', 'user_id', 'skill_id')
                    ->withPivot('id', 'proficiency', 'hourly_rate', 'travel_fee', 'meta')
                    ->withTimestamps();
    }

    public function locations()
    {
        return $this->hasMany(\App\Modules\Profile\Models\Location::class);
    }

    public function schedule()
    {
        return $this->hasOne(\App\Modules\Schedule\Models\Schedule::class);
    }

    /**
     * Surcharge de l'envoi de la notification de vérification d'e-mail.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification());
    }
}
