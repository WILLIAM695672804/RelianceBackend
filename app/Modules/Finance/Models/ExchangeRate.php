<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'from_currency', 'to_currency',
        'rate', 'active', 'set_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
