<?php

namespace App\Modules\Governance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlaRule extends Model
{
    protected $table = 'sla_rules';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'event_key',
        'penalty_type',
        'penalty_value',
        'active',
        'meta',
    ];

    protected $casts = [
        'penalty_value' => 'decimal:2',
        'active'        => 'boolean',
        'meta'          => 'array',
        'created_at'    => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function events()
    {
        return $this->hasMany(SlaEvent::class, 'event_key', 'event_key');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForEventKey($query, string $eventKey)
    {
        return $query->where('event_key', $eventKey);
    }
}
