<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class BookingItem extends Model
{
    protected $fillable = ['booking_id', 'service_id', 'price', 'quantity'];

    protected function casts(): array
    {
        return ['price' => 'float', 'quantity' => 'integer'];
    }

    protected static function booted(): void
    {
        // Jaring pengaman: layanan harus milik bisnis yang sama dengan booking.
        static::creating(function (BookingItem $item) {
            $bookingBusiness = Booking::whereKey($item->booking_id)->value('business_id');
            $serviceBusiness = Service::whereKey($item->service_id)->value('business_id');

            if ($bookingBusiness === null || $serviceBusiness === null || (int) $bookingBusiness !== (int) $serviceBusiness) {
                throw new InvalidArgumentException('Layanan tidak dimiliki oleh bisnis pada booking ini.');
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getSubtotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }
}
