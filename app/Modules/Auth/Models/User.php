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
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

/**
 * @method bool hasVerifiedEmail()
 * @method void sendEmailVerificationNotification()
 */

class User extends Authenticatable implements MustVerifyEmail, AuditableContract
{
    use HasApiTokens, Notifiable, SoftDeletes, HasRoles, Auditable;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
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
}
