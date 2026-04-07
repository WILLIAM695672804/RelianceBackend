<?php
namespace App\Modules\Gamification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReputationChange extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['user_id', 'change', 'reason'];

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
}
