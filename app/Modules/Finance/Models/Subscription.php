<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'subscriber_id', 'plan_name', 'status',
        'start_date', 'end_date', 'recurring', 'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'recurring' => 'boolean',
        'metadata' => 'array',
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

    public function subscriber()
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === 'trial';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function pause(): void
    {
        if (!$this->isActive()) {
            throw new \DomainException('Seul un abonnement actif peut être mis en pause.');
        }

        $this->update([
            'status' => 'paused',
            'metadata' => array_merge($this->metadata ?? [], [
                'paused_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function resume(): void
    {
        if (!$this->isPaused()) {
            throw new \DomainException('Seul un abonnement en pause peut être repris.');
        }

        $pausedAt = $this->metadata['paused_at'] ?? null;
        $pausedDays = $pausedAt ? now()->diffInDays($pausedAt) : 0;

        $this->update([
            'status' => 'active',
            'end_date' => $this->end_date?->addDays($pausedDays),
            'metadata' => array_merge($this->metadata ?? [], [
                'resumed_at' => now()->toIso8601String(),
                'paused_days' => $pausedDays,
            ]),
        ]);
    }
}
