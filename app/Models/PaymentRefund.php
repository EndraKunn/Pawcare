<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = ['payment_id','requested_by','amount','reason','provider_reference','status','idempotency_key','response_payload','processed_at'];
    protected function casts(): array { return ['amount'=>'decimal:2','response_payload'=>'array','processed_at'=>'datetime']; }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
