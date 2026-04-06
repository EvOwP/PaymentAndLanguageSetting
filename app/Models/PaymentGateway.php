<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = ['name', 'is_manual', 'currency', 'fee', 'status', 'credentials', 'instructions', 'logo'];

    protected $casts = [
        'credentials' => 'array',
        'is_manual' => 'boolean',
        'status' => 'boolean',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
