<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUS_PENDING_PAYMENT = 'Menunggu Pembayaran';
    public const STATUS_PAID = 'Dibayar';
    public const STATUS_CANCELLED = 'Dibatalkan';

    protected $fillable = [
        'order_number',
        'customer_id',
        'idempotency_key',
        'request_hash',
        'status',
        'total_amount',
        'snapshot',
        'checked_out_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'float',
            'snapshot' => 'array',
            'checked_out_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->orderBy('id');
    }

    public function scopeForCustomer(Builder $query, User $customer): Builder
    {
        return $query->where('customer_id', $customer->id);
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }
}
