<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Matching\Models\ClientRequest;

class Quote extends Model
{
    protected $table = 'quotes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_id',
        'issuer_id',
        'total_amount',
        'breakdown',
        'status',
        'valid_until',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'breakdown'    => 'array',
        'valid_until'  => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function request()
    {
        return $this->belongsTo(ClientRequest::class, 'request_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'quote_id');
    }

    public function isAcceptable(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }
}
