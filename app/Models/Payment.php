<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Payment extends Model
{
    use SoftDeletes;
    
    // Status Constants
    const STATUS_PENDING            = 'pending';
    const STATUS_PROCESSING         = 'processing';
    const STATUS_PAID               = 'paid';
    const STATUS_FAILED             = 'failed';
    const STATUS_REFUNDED           = 'refunded';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    const STATUS_EXPIRED            = 'expired';
    const STATUS_CANCELLED          = 'cancelled';

    /**
     * Allowed transitions for each state
     */
    protected static $allowedTransitions = [
        self::STATUS_PENDING => [
            self::STATUS_PROCESSING,
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED
        ],
        self::STATUS_PROCESSING => [
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED
        ],
        self::STATUS_PAID => [
            self::STATUS_REFUNDED,
            self::STATUS_PARTIALLY_REFUNDED
        ],
        self::STATUS_PARTIALLY_REFUNDED => [
            self::STATUS_REFUNDED,
            self::STATUS_PARTIALLY_REFUNDED
        ],
        self::STATUS_FAILED   => [],
        self::STATUS_REFUNDED => [],
        self::STATUS_EXPIRED  => [],
        self::STATUS_CANCELLED => [],
    ];

    /**
     * Check if a transition to a new status is allowed
     */
    public function canTransitionTo($newStatus)
    {
        return in_array($newStatus, self::$allowedTransitions[$this->status] ?? []);
    }

    /**
     * Transition a payment to a new status safely
     */
    public function transitionTo($newStatus, $updateData = [])
    {
        if (!$this->canTransitionTo($newStatus)) {
            Log::warning("Unauthorized state transition attempt: [{$this->status} -> {$newStatus}] for Payment #{$this->id}");
            return false;
        }

        $updateData['status'] = $newStatus;
        return $this->update($updateData);
    }

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
        'settlement_reference',
        'is_fraud',
        'risk_score',
        'webhook_payload',
        'proof_path',
        'customer_email',
        'notes'
    ];

    protected $casts = [
        'webhook_payload' => 'array',
        'is_fraud' => 'boolean',
        'risk_score' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::updated(function ($model) {
            if ($model->isDirty('status')) {
                PaymentStatusHistory::create([
                    'payment_id' => $model->id,
                    'old_status' => $model->getOriginal('status'),
                    'new_status' => $model->status,
                    'changed_at' => now(),
                ]);

                if ($model->status === self::STATUS_PAID) {
                    \App\Events\PaymentSuccess::dispatch($model);
                    Log::info("PaymentSuccess event dispatched", ['payment_uuid' => $model->uuid]);
                }
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

    public function user()
    {
        return $this->belongsTo(User::class);
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

    public function statusHistories()
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    public function getTotalRefundedAttribute()
    {
        return $this->refunds()->where('status', 'completed')->sum('amount');
    }
}
