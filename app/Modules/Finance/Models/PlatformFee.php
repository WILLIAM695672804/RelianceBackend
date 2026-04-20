<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformFee extends Model
{
    use HasFactory;

    protected $table = 'platform_fees';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'name', 'fee_type', 'value',
        'applies_to', 'active', 'meta',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'active' => 'boolean',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function calculateFee(float $baseAmount): float
    {
        if ($this->fee_type === 'percentage') {
            return round($baseAmount * $this->value, 2);
        }
        return (float) $this->value;
    }
}
