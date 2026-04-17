<?php

namespace App\Modules\Social\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;

class Message extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachments',
        'delivered',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'delivered'   => 'boolean',
        'created_at'  => 'datetime',
        'read_at'     => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
