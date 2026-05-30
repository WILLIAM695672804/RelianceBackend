<?php

namespace App\Modules\Matching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MlModel extends Model
{
    protected $table = 'ml_models';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'name', 'version', 'model_ref', 'metadata', 'deployed',
    ];

    protected $casts = [
        'metadata' => 'array',
        'deployed' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function trainingLogs()
    {
        return $this->hasMany(MlTrainingLog::class, 'model_id');
    }
}
