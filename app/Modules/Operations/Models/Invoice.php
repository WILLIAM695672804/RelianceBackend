<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;

class Invoice extends Model
{
    protected $table = 'invoices';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'quote_id',
        'client_id',
        'issuer_id',
        'amount',
        'tax_amount',
        'reference',
        'external_pdf_ref',
        'status',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
            if (!$model->reference) {
                $model->reference = 'INV-' . strtoupper(Str::random(8));
            }
        });
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }
}
