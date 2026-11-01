<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessClosure extends Model
{
    protected $fillable = [
        'business_id',
        'closed_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'closed_date' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}