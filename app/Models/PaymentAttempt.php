<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'payment_id','attempt_number','provider','channel','idempotency_key',
        'gateway_order_id','gateway_transaction_id','amount','currency','status',
        'qr_code_url','payment_url','request_payload','response_payload',
        'expires_at','settled_at','failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'expires_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
