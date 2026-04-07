<?php
namespace App\Modules\Gamification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserBadge extends Model
{
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['user_id', 'badge_id', 'awarded_at', 'meta'];
    protected $casts = ['awarded_at' => 'datetime', 'meta' => 'array'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }
}
