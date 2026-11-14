<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    public const REQUESTED='requested', APPROVED='approved', REJECTED='rejected', PAID='paid';
    protected $fillable = ['business_id','requested_by','amount','bank_name','bank_account_number','bank_account_name','status','idempotency_key','approved_by','processed_by','reference','rejection_reason','processed_at'];
    protected function casts(): array { return ['amount'=>'decimal:2','processed_at'=>'datetime']; }
    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class,'requested_by'); }
}
