<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Matching\Models\ClientRequest;

class Intervention extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'job_id', 'technician_id', 'request_id', 'type', 'status',
        'description', 'work_performed', 'notes', 'materials_used',
        'duration_hours', 'scheduled_at', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'materials_used' => 'array',
        'duration_hours' => 'decimal:2',
        'scheduled_at'   => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function job()
    {
        return $this->belongsTo(TechnicianJob::class, 'job_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function request()
    {
        return $this->belongsTo(ClientRequest::class, 'request_id');
    }
}
