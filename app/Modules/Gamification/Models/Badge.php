<?php
namespace App\Modules\Gamification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Badge extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['code', 'title', 'description', 'icon_ref', 'criteria'];
    protected $casts = ['criteria' => 'array'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }
}
