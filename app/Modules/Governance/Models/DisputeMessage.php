<?php

namespace App\Modules\Governance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class DisputeMessage extends Model
{
    protected $table = 'dispute_messages';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'dispute_id', 'sender_id', 'message', 'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function dispute()
    {
        return $this->belongsTo(Dispute::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
