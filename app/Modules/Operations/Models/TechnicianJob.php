<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Matching\Models\ClientRequest;

class TechnicianJob extends Model
{
    protected $table = 'technician_jobs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'technician_id',
        'appointment_id',
        'status',
        'route',
        'estimated_travel_seconds',
        'phases',
        'completion_notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'route'      => 'array',
        'phases'     => 'array',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
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

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
