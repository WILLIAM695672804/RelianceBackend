<?php

namespace App\Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class NotificationSetting extends Model
{
    protected $table = 'notification_settings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'channel', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
