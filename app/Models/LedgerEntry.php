<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    public $timestamps = false;
    protected $fillable = ['entry_group_id','business_id','account_code','debit','credit','source_type','source_id','idempotency_key','description','metadata','created_at'];
    protected function casts(): array { return ['debit'=>'decimal:2','credit'=>'decimal:2','metadata'=>'array','created_at'=>'datetime']; }
    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
}
