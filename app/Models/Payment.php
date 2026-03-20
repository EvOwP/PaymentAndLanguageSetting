<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'uuid',
        'idempotency_key',
        'payable_id',
        'payable_type',
        'user_id',
        'payment_gateway_id',
        'amount',
        'currency',
        'original_amount',
        'original_currency',
        'exchange_rate',
        'status',
        'fee',
        'net_amount',
        'fee_bearer',
        'settlement_status',
        'settled_at',
        'webhook_payload',
        'proof_path',
        'customer_email',
        'notes'
    ];

    protected $casts = [
        'webhook_payload' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function payable()
    {
        return $this->morphTo();
    }

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function logs()
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}
