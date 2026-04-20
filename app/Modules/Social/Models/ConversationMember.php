<?php

namespace App\Modules\Social\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;

class ConversationMember extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'unread_count',
    ];

    protected $casts = [
        'joined_at'    => 'datetime',
        'unread_count' => 'integer',
    ];

    public function getKeyName()
    {
        return ['conversation_id', 'user_id'];
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
