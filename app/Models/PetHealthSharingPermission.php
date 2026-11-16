<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetHealthSharingPermission extends Model
{
    protected $fillable = [
        'pet_health_record_id',
        'business_id',
        'booking_id',
        'granted_by',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function healthRecord(): BelongsTo
    {
        return $this->belongsTo(PetHealthRecord::class, 'pet_health_record_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isActive(): bool
    {
        return $this->granted_at !== null && $this->revoked_at === null;
    }
}
