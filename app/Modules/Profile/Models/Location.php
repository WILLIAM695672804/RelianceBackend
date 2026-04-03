<?php

namespace App\Modules\Profile\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $table = 'locations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'label',
        'address',
        'city',
        'state',
        'country',
        'postcode',
        'is_default',
        'meta',
        // 'geom' is generally updated via raw queries or specific spatial packages in eloquent
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($location) {
            if (!$location->id) {
                $location->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
