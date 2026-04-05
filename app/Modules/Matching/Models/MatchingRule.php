<?php

namespace App\Modules\Matching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MatchingRule extends Model
{
    protected $table = 'matching_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'priority',
        'enabled',
        'rule_json',
    ];

    protected $casts = [
        'enabled'  => 'boolean',
        'rule_json' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
