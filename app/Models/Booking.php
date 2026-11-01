<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const STATUS_WAITING = 'Menunggu';
    public const STATUS_CONFIRMED = 'Dikonfirmasi';
    public const STATUS_PROGRESS = 'Dalam Proses';
    public const STATUS_DONE = 'Selesai';
    public const STATUS_CANCELLED = 'Dibatalkan';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_CONFIRMED,
        self::STATUS_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    /** Perubahan status yang diizinkan untuk Business Owner. */
    public const TRANSITIONS = [
        self::STATUS_WAITING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_PROGRESS => [self::STATUS_DONE, self::STATUS_CANCELLED],
        self::STATUS_DONE => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'business_id',
        'pet_id',
        'booking_date',
        'booking_time',
        'booking_end_time',
        'duration_minutes',
        'status',
        'total_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'total_price' => 'float',
            'duration_minutes' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function careNotes(): HasMany
    {
        return $this->hasMany(PetCareNote::class)->latest('id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class)->orderBy('changed_at')->orderBy('id');
    }

    public function scopeForOwner(Builder $query, User $owner): Builder
    {
        return $query->whereHas('business', fn ($b) => $b->where('owner_id', $owner->id));
    }

    public function scopeForCustomer(Builder $query, User $customer): Builder
    {
        return $query->where('customer_id', $customer->id);
    }

    public function allowedNextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function canBeReviewedBy(User $customer): bool
    {
        return (int) $this->customer_id === (int) $customer->id
            && $this->status === self::STATUS_DONE
            && ! $this->review()->exists();
    }

    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_WAITING => 'wait',
            self::STATUS_CONFIRMED => 'conf',
            self::STATUS_PROGRESS => 'prog',
            self::STATUS_DONE => 'done',
            default => 'bad',
        };
    }

    public function getTimeLabelAttribute(): string
    {
        return substr((string) $this->booking_time, 0, 5);
    }

    public function getEndTimeLabelAttribute(): string
    {
        if ($this->booking_end_time) {
            return substr((string) $this->booking_end_time, 0, 5);
        }

        $start = substr((string) $this->booking_time, 0, 5);
        [$h, $m] = array_pad(explode(':', $start), 2, '0');
        $totalMinutes = ((int) $h) * 60 + ((int) $m) + max(15, (int) ($this->duration_minutes ?: 60));

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60) % 24, $totalMinutes % 60);
    }

    public function getScheduleLabelAttribute(): string
    {
        return $this->booking_date->translatedFormat('d M Y').', '.$this->time_label;
    }

    /** Nomor invoice unik: INV-YYYYMMDD-NNNN. */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $seq = static::where('invoice_number', 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (static::where('invoice_number', $number)->exists());

        return $number;
    }
}
