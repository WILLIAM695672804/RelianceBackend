<?php

namespace App\Modules\Governance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'object_type',
        'object_id',
        'action',
        'details',
    ];

    protected $casts = [
        'details'    => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForActor($query, string $actorId)
    {
        return $query->where('actor_id', $actorId);
    }

    public function scopeForObject($query, string $type, ?string $id = null)
    {
        $query->where('object_type', $type);
        if ($id) {
            $query->where('object_id', $id);
        }
        return $query;
    }

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
