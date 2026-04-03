<?php

namespace App\Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleException extends Model
{
    use HasFactory;

    protected $table = 'schedule_exceptions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'schedule_id',
        'start_ts',
        'end_ts',
        'reason',
    ];

    protected $casts = [
        'start_ts' => 'datetime',
        'end_ts' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($exception) {
            if (!$exception->id) {
                $exception->id = (string) Str::uuid();
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
