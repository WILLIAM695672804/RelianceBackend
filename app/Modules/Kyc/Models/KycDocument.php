<?php

namespace App\Modules\Kyc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Support\SignedFileUrl;

class KycDocument extends Model
{
    protected $table = 'kyc_documents';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'status',
        'rejection_reason',
        'validated_by',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /** URL temporaire du fichier (stocké sur le disque privé), voir SignedFileUrl. */
    public function temporaryUrl(int $minutes = 10): string
    {
        return SignedFileUrl::make('kyc.fichier', 'kyc', $this->id, $minutes);
    }

    /**
     * Relation vers l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}