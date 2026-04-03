<?php

namespace App\Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TimeSlot extends Model
{
    use HasFactory;

    protected $table = 'time_slots';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'schedule_id',
        'day_of_week',
        'start_time',
        'end_time',
        'repeat_weekly',
        'meta',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'repeat_weekly' => 'boolean',
        'meta' => 'json',
    ];

    protected static function booted()
    {
        static::creating(function ($slot) {
            if (!$slot->id) {
                $slot->id = (string) Str::uuid();
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
