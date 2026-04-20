<?php

namespace App\Modules\Matching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Profile\Models\Location;
use App\Modules\Profile\Models\Skill;

class ClientRequest extends Model
{
    protected $table = 'client_requests';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id',
        'title',
        'description',
        'address_id',
        'detected_category',
        'status',
        'diagnostic_fee',
        'photos',
        'metadata',
    ];

    protected $casts = [
        'photos'         => 'array',
        'metadata'       => 'array',
        'diagnostic_fee' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function address()
    {
        return $this->belongsTo(Location::class, 'address_id');
    }

    public function category()
    {
        return $this->belongsTo(Skill::class, 'detected_category');
    }

    public function analysis()
    {
        return $this->hasOne(RequestAnalysis::class, 'request_id');
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class, 'request_id');
    }

    public function matchingLogs()
    {
        return $this->hasMany(MatchingLog::class, 'request_id');
    }

    public function quotes()
    {
        return $this->hasMany(\App\Modules\Operations\Models\Quote::class, 'request_id');
    }

    public function appointments()
    {
        return $this->hasMany(\App\Modules\Operations\Models\Appointment::class, 'request_id');
    }

    public function jobs()
    {
        return $this->hasMany(\App\Modules\Operations\Models\TechnicianJob::class, 'request_id');
    }
}
