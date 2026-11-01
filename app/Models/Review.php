<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Review extends Model
{
    protected $fillable = [
        'booking_id',
        'customer_id',
        'business_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    public function scopeForOwner(Builder $query, User $owner): Builder
    {
        return $query->whereHas('business', fn ($b) => $b->where('owner_id', $owner->id));
    }

    public function scopeForCustomer(Builder $query, User $customer): Builder
    {
        return $query->where('customer_id', $customer->id);
    }

    /**
     * Hanya ulasan yang terhubung dengan booking berstatus Selesai yang dihitung sah.
     */
    public function scopeValidCompleted(Builder $query): Builder
    {
        return $query->whereBetween('rating', [1, 5])
            ->whereHas('booking', fn ($b) => $b->where('status', Booking::STATUS_DONE));
    }

    public function getStarsLabelAttribute(): string
    {
        $r = max(1, min(5, (int) $this->rating));

        return str_repeat('★', $r).str_repeat('☆', 5 - $r);
    }

    /**
     * Nama pelanggan disamarkan sebagian di halaman publik untuk menjaga privasi.
     */
    public function getCustomerPublicNameAttribute(): string
    {
        $name = trim((string) ($this->customer?->name ?? 'Pelanggan'));
        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) <= 1) {
            return $name;
        }

        return $parts[0].' '.mb_substr($parts[1], 0, 1).'.';
    }
}
