<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'idempotency_key',
        'provider',
        'order_id',
        'transaction_status',
        'status_code',
        'gross_amount',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'float',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
