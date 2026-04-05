<?php

namespace App\Modules\Matching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;

class Proposal extends Model
{
    protected $table = 'proposals';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'technician_id',
        'price',
        'message',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'expires_at' => 'datetime',
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
}
