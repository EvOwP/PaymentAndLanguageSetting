<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = ['payment_id', 'payment_transaction_id', 'amount', 'currency', 'reason', 'status', 'external_refund_id'];

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
