<?php

namespace App\Modules\Matching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;

class MatchingLog extends Model
{
    protected $table = 'matching_logs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'chosen_technician_id',
        'candidates',
        'scoring_details',
    ];

    protected $casts = [
        'candidates'      => 'array',
        'scoring_details' => 'array',
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

    public function chosenTechnician()
    {
        return $this->belongsTo(User::class, 'chosen_technician_id');
    }
}
