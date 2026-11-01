<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetCareNote extends Model
{
    protected $fillable = [
        'booking_id',
        'pet_id',
        'business_id',
        'created_by',
        'care_summary',
        'recommendations',
        'visible_to_customer',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_customer' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
