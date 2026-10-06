<?php

namespace App\Modules\Profile\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class Agency extends Model
{
    use HasFactory;

    protected $table = 'agencies';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'contact_email',
        'contact_phone',
        'address',
    ];

    protected static function booted()
    {
        static::creating(function ($agency) {
            if (!$agency->id) {
                $agency->id = (string) Str::uuid();
            }
        });
    }

    public function memberships()
    {
        return $this->hasMany(AgencyMembership::class, 'agency_id');
    }

    /** Un administrateur de l'agence : le créateur l'est d'office. */
    public function isAdmin(string $userId): bool
    {
        return $this->memberships()
            ->where('user_id', $userId)
            ->where('role', 'admin')
            ->exists();
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'agency_memberships', 'agency_id', 'user_id')
                    ->withPivot('role', 'joined_at')
                    ->withTimestamps();
    }
}
