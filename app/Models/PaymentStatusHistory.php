<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'old_status',
        'new_status',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
