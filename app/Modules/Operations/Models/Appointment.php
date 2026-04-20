<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Profile\Models\Location;

class Appointment extends Model
{
    protected $table = 'appointments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'technician_id',
        'scheduled_start',
        'scheduled_end',
        'location_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end'   => 'datetime',
        'assigned_at'     => 'datetime',
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

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function job()
    {
        return $this->hasOne(TechnicianJob::class, 'appointment_id');
    }
}
