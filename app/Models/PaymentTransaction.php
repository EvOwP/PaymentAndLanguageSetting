<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_id',
        'external_id',
        'status',
        'amount',
        'currency',
        'payload',
        'error_code',
        'error_message'
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}
