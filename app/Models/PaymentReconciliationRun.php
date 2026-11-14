<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliationRun extends Model
{
    protected $fillable=['provider','started_by','status','checked_count','matched_count','mismatch_count','details','started_at','finished_at'];
    protected function casts(): array { return ['details'=>'array','started_at'=>'datetime','finished_at'=>'datetime']; }
    public function starter(): BelongsTo { return $this->belongsTo(User::class,'started_by'); }
}
