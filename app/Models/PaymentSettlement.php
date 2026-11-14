<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSettlement extends Model
{
    protected $fillable = ['payment_id','business_id','reference','gross_amount','platform_fee','net_amount','source','status','settled_at'];
    protected function casts(): array { return ['gross_amount'=>'decimal:2','platform_fee'=>'decimal:2','net_amount'=>'decimal:2','settled_at'=>'datetime']; }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
}
