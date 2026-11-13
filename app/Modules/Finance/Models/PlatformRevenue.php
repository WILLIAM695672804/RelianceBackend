<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Ligne du journal des revenus de la plateforme (commission, abonnement…). */
class PlatformRevenue extends Model
{
    protected $table = 'platform_revenues';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['payment_id', 'source', 'amount', 'currency'];

    protected $casts = ['amount' => 'decimal:2', 'created_at' => 'datetime'];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
            $model->created_at = $model->created_at ?: now();
        });
    }
}
