<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const UNPAID = 'Belum Dibayar';
    public const PENDING = 'Menunggu Verifikasi';
    public const PAID = 'Dibayar';
    public const REJECTED = 'Ditolak';

    public const STATUSES = [self::UNPAID, self::PENDING, self::PAID, self::REJECTED];

    public const METHOD_MANUAL = 'manual_transfer';
    public const METHOD_GATEWAY = 'payment_gateway';

    public const GATEWAY_PENDING = 'pending';
    public const GATEWAY_SETTLEMENT = 'settlement';
    public const GATEWAY_EXPIRE = 'expire';
    public const GATEWAY_CANCEL = 'cancel';
    public const GATEWAY_DENY = 'deny';

    public const PROOF_DISK = 'local';
    public const PROOF_DIRECTORY = 'payment-proofs';
    public const PROOF_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    public const PROOF_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    protected $fillable = [
        'booking_id',
        'amount',
        'payment_method',
        'gateway_provider',
        'gateway_reference',
        'gateway_transaction_id',
        'gateway_payment_type',
        'gateway_status',
        'gateway_payment_url',
        'gateway_expires_at',
        'proof_path',
        'status',
        'reject_reason',
        'verified_by',
        'verified_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'gateway_expires_at' => 'datetime',
        ];
    }

    /** Aturan validasi unggah bukti pembayaran (JPG, JPEG, PNG, WebP, PDF). */
    public static function proofRules(int $maxKb = 2048): array
    {
        return [
            'required',
            'file',
            'mimes:'.implode(',', self::PROOF_EXTENSIONS),
            'mimetypes:'.implode(',', array_keys(self::PROOF_MIME_TYPES)),
            'max:'.$maxKb,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Riwayat percobaan pembayaran.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->latest('id');
    }

    public function gatewayLogs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class)->orderByDesc('id');
    }

    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            self::PENDING => 'wait pay',
            self::PAID => 'done pay',
            self::REJECTED => 'bad pay',
            default => 'gray pay',
        };
    }

    public function getGatewayStatusLabelAttribute(): ?string
    {
        return match ($this->gateway_status) {
            self::GATEWAY_PENDING => 'Menunggu Pembayaran Gateway',
            self::GATEWAY_SETTLEMENT => 'Lunas (Gateway Terverifikasi)',
            self::GATEWAY_EXPIRE => 'Kedaluwarsa',
            self::GATEWAY_CANCEL => 'Dibatalkan',
            self::GATEWAY_DENY => 'Gagal / Ditolak Gateway',
            default => null,
        };
    }

    public function getTransactionCodeAttribute(): ?string
    {
        if ($this->status !== self::PAID || ! $this->paid_at) {
            return null;
        }

        return 'TRX-'.$this->paid_at->format('Ymd').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
