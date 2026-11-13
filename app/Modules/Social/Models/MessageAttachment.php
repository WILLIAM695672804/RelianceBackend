<?php

namespace App\Modules\Social\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\SignedFileUrl;
use Illuminate\Support\Str;

class MessageAttachment extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'conversation_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    // Horodatage géré manuellement ($timestamps = false) : sans conversion, created_at serait relu comme chaîne.
    protected $casts = ['created_at' => 'datetime'];

    protected $appends = ['url'];

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

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * URL temporaire (une heure) : les pièces jointes d'une conversation privée sont stockées sur le
     * disque privé et ne sont servies que sur signature, voir SignedFileUrl.
     */
    public function getUrlAttribute(): string
    {
        return SignedFileUrl::make('messagerie.piece-jointe', 'piece-jointe', $this->id, 60);
    }
}
