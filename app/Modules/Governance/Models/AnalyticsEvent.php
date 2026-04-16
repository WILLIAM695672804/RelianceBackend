<?php

namespace App\Modules\Governance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;

class AnalyticsEvent extends Model
{
    protected $table = 'analytics_events';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_name',
        'event_props',
    ];

    protected $casts = [
        'event_props' => 'array',
        'created_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForEvent($query, string $name)
    {
        return $query->where('event_name', $name);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
