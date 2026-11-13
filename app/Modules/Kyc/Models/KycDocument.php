<?php

namespace App\Modules\Kyc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;

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

    /**
     * URL temporaire du fichier, signée par HMAC (le fichier est stocké sur le disque privé).
     * La signature est propre au module : la vérification des URL signées de Laravel 10 est
     * sujette à une confusion de chemin non corrigée sur cette branche.
     */
    public function temporaryUrl(int $minutes = 10): string
    {
        $expires = now()->addMinutes($minutes)->getTimestamp();

        return route('kyc.fichier', [
            'id' => $this->id,
            'expires' => $expires,
            'signature' => self::signature($this->id, $expires),
        ]);
    }

    public static function signature(string $id, int $expires): string
    {
        return hash_hmac('sha256', "kyc|{$id}|{$expires}", (string) config('app.key'));
    }

    /**
     * Relation vers l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}