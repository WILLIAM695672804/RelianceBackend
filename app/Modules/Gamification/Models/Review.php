<?php
namespace App\Modules\Gamification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Review extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['reviewer_id', 'target_id', 'request_id', 'rating', 'comment', 'stage'];
    protected $casts = ['rating' => 'integer'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'reviewer_id');
    }

    public function target()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'target_id');
    }

    public function request()
    {
        return $this->belongsTo(\App\Modules\Matching\Models\ClientRequest::class, 'request_id');
    }
}
