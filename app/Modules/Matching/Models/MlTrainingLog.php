<?php

namespace App\Modules\Matching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MlTrainingLog extends Model
{
    protected $table = 'ml_training_logs';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'model_id', 'dataset_ref', 'metrics', 'run_at', 'notes',
    ];

    protected $casts = [
        'metrics' => 'array',
        'run_at'  => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function model()
    {
        return $this->belongsTo(MlModel::class, 'model_id');
    }
}
