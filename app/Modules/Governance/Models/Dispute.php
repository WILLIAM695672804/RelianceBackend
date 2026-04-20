<?php

namespace App\Modules\Governance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use App\Modules\Matching\Models\ClientRequest;
use Illuminate\Support\Str;

class Dispute extends Model
{
    protected $table = 'disputes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_id', 'opened_by', 'against',
        'reason', 'description', 'status',
        'resolution', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
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

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function defendant()
    {
        return $this->belongsTo(User::class, 'against');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function messages()
    {
        return $this->hasMany(DisputeMessage::class);
    }
}
