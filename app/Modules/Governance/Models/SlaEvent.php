<?php

namespace App\Modules\Governance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Matching\Models\ClientRequest;

class SlaEvent extends Model
{
    protected $table = 'sla_events';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'event_key',
        'occurred_at',
        'payload',
    ];

    protected $casts = [
        'payload'     => 'array',
        'occurred_at' => 'datetime',
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

    public function scopeForEventKey($query, string $eventKey)
    {
        return $query->where('event_key', $eventKey);
    }
}
