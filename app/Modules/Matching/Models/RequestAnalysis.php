<?php

namespace App\Modules\Matching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RequestAnalysis extends Model
{
    protected $table = 'request_analyses';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'analyzed_at',
        'estimated_skills',
        'estimated_difficulty',
        'suggested_price',
        'payload',
    ];

    protected $casts = [
        'analyzed_at'   => 'datetime',
        'estimated_skills' => 'array',
        'suggested_price'  => 'decimal:2',
        'payload'          => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function request()
    {
        return $this->belongsTo(ClientRequest::class, 'request_id');
    }
}
