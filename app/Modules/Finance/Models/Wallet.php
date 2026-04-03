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
}
