<?php

namespace App\Modules\Profile\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;

    protected $table = 'skills';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug',
        'name',
        'category',
    ];

    protected static function booted()
    {
        static::creating(function ($skill) {
            if (!$skill->id) {
                $skill->id = (string) Str::uuid();
            }
        });
    }
}
