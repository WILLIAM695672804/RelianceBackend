<?php

namespace App\Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class Schedule extends Model
{
    use HasFactory;

    protected $table = 'schedules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'tz',
    ];

    protected static function booted()
    {
        static::creating(function ($schedule) {
            if (!$schedule->id) {
                $schedule->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function timeSlots()
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function exceptions()
    {
        return $this->hasMany(ScheduleException::class);
    }
}
