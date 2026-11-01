<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessOperatingHour extends Model
{
    protected $fillable = [
        'business_id',
        'day_of_week',
        'is_closed',
        'opens_at',
        'closes_at',
        'slot_duration_minutes',
        'slot_capacity',
        'break_starts_at',
        'break_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
            'slot_duration_minutes' => 'integer',
            'slot_capacity' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}