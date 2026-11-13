<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'wallets';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'balance',
        'currency',
    ];

    protected static function booted()
    {
        static::creating(function ($wallet) {
            if (!$wallet->id) {
                $wallet->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function holds()
    {
        return $this->hasMany(Hold::class);
    }

    public function activeHolds()
    {
        return $this->hasMany(Hold::class)->where('status', 'held');
    }

    /** Portefeuille principal (XAF) : celui sur lequel reposent séquestre, abonnements et paiements. */
    public function scopeXaf($query)
    {
        return $query->where('currency', 'XAF');
    }

    public function availableBalance(): float
    {
        $heldAmount = $this->activeHolds()->sum('amount');
        return (float) $this->balance - $heldAmount;
    }
}
