<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Notifications\PawCareStatusNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaymentGatewayService
{
    public const PROVIDER = 'midtrans';

    public const CHANNELS = [
        'qris' => 'QRIS dinamis',
        'bca_va' => 'BCA Virtual Account',
        'bri_va' => 'BRI Virtual Account',
        'mandiri_va' => 'Mandiri Bill Payment',
        'bni_va' => 'BNI Virtual Account',
    ];

    public function serverKey(): string
    {
        return (string) config('services.midtrans.server_key', '');
    }

    private function apiBase(): string
    {
        return (bool) config('services.midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    /**
     * Membuat transaksi Midtrans Core API.
     */
    public function createTransaction(
        Booking $booking,
        User $customer,
        string $channel = 'qris'
    ): Payment {
        if ((int) $booking->customer_id !== (int) $customer->id) {
            throw new AccessDeniedHttpException(
                'Anda tidak berhak mengakses pembayaran booking ini.'
            );
        }

        if ($booking->status === Booking::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'payment' => 'Booking yang dibatalkan tidak dapat dibayar.',
            ]);
        }

        $payment = $booking->payment;

        if (! $payment || $payment->status === Payment::PAID) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran tidak tersedia atau sudah lunas.',
            ]);
        }

        if (! array_key_exists($channel, self::CHANNELS)) {
            throw ValidationException::withMessages([
                'channel' => 'Metode pembayaran tidak didukung.',
            ]);
        }

        if (! filled($this->serverKey())) {
            throw ValidationException::withMessages([
                'payment' => 'MIDTRANS_SERVER_KEY belum diatur.',
            ]);
        }

        /*
         * Membuat payment attempt dan menyimpan referensi
         * sebelum request ke Midtrans agar webhook tidak kehilangan referensi.
         */
        [$attempt, $amount, $orderId, $payload] = DB::transaction(
            function () use ($booking, $customer, $payment, $channel) {
                $lockedBooking = Booking::query()
                    ->lockForUpdate()
                    ->findOrFail($booking->id);

                if (
                    (int) $lockedBooking->customer_id !==
                    (int) $customer->id
                ) {
                    throw new AccessDeniedHttpException(
                        'Anda tidak berhak mengakses pembayaran booking ini.'
                    );
                }

                if ($lockedBooking->status === Booking::STATUS_CANCELLED) {
                    throw ValidationException::withMessages([
                        'payment' => 'Booking telah dibatalkan.',
                    ]);
                }

                $lockedPayment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ($lockedPayment->status === Payment::PAID) {
                    throw ValidationException::withMessages([
                        'payment' => 'Pembayaran sudah lunas.',
                    ]);
                }

                $amount = (int) round(
                    (float) $lockedBooking->total_price
                );

                if ($amount < 1) {
                    throw ValidationException::withMessages([
                        'payment' => 'Nominal pembayaran tidak valid.',
                    ]);
                }

                $attemptNumber = (int) PaymentAttempt::query()
                    ->where('payment_id', $lockedPayment->id)
                    ->max('attempt_number') + 1;

                $orderId = 'PAW-' . $lockedPayment->id . '-'
                    . now()->format('YmdHis') . '-'
                    . Str::upper(Str::random(5));

                $paymentType = match ($channel) {
                    'qris' => 'qris',
                    'mandiri_va' => 'echannel',
                    default => 'bank_transfer',
                };

                $payload = [
                    'payment_type' => $paymentType,
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $amount,
                    ],
                    'customer_details' => [
                        'first_name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ],
                ];

                if ($channel === 'qris') {
                    $payload['qris'] = [
                        'acquirer' => 'gopay',
                    ];
                } elseif ($channel === 'mandiri_va') {
                    $payload['echannel'] = [
                        'bill_info1' => 'PawCare',
                        'bill_info2' => 'Pembayaran booking',
                    ];
                } else {
                    $bank = match ($channel) {
                        'bca_va' => 'bca',
                        'bri_va' => 'bri',
                        'bni_va' => 'bni',
                        default => null,
                    };

                    $payload['bank_transfer'] = [
                        'bank' => $bank,
                    ];
                }

                // Jangan simpan informasi pribadi pelanggan dalam audit request.
                $storedPayload = $payload;
                unset($storedPayload['customer_details']);

                $idempotencyKey = (string) Str::uuid();
                $expiresAt = now()->addHours(24);

                $attempt = PaymentAttempt::create([
                    'payment_id' => $lockedPayment->id,
                    'attempt_number' => $attemptNumber,
                    'provider' => self::PROVIDER,
                    'channel' => $channel,
                    'idempotency_key' => $idempotencyKey,
                    'gateway_order_id' => $orderId,
                    'amount' => $amount,
                    'currency' => 'IDR',
                    'status' => 'creating',
                    'expires_at' => $expiresAt,
                    'request_payload' => $storedPayload,
                ]);

                $lockedPayment->update([
                    'amount' => $amount,
                    'payment_method' => Payment::METHOD_GATEWAY,
                    'gateway_provider' => self::PROVIDER,
                    'gateway_reference' => $orderId,
                    'gateway_transaction_id' => null,
                    'gateway_payment_type' => $channel,
                    'gateway_status' => Payment::GATEWAY_PENDING,
                    'gateway_payment_url' => null,
                    'gateway_expires_at' => $expiresAt,
                    'status' => Payment::UNPAID,
                    'reject_reason' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                    'paid_at' => null,
                ]);

                return [
                    $attempt,
                    $amount,
                    $orderId,
                    $payload,
                ];
            }
        );

        /*
         * Request Midtrans Core API Charge.
         */
        try {
            $response = Http::withBasicAuth(
                $this->serverKey(),
                ''
            )
                ->withHeaders([
                    'Idempotency-Key' => $attempt->idempotency_key,
                ])
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post(
                    $this->apiBase() . '/v2/charge',
                    $payload
                );
        } catch (\Throwable $exception) {
            report($exception);

            $this->markAttemptFailed(
                $attempt,
                $payment,
                ['error' => 'Gateway connection failed']
            );

            throw ValidationException::withMessages([
                'payment' => 'Midtrans tidak dapat dihubungi. Silakan coba lagi.',
            ]);
        }

        $body = $response->json();

        if (! is_array($body)) {
            $body = [];
        }

        if (
            ! $response->successful() ||
            empty($body['transaction_id'])
        ) {
            $this->markAttemptFailed(
                $attempt,
                $payment,
                $body
            );

            throw ValidationException::withMessages([
                'payment' => 'Midtrans gagal membuat transaksi: '
                    . ($body['status_message'] ?? 'Periksa konfigurasi Sandbox.'),
            ]);
        }

        /*
         * Ambil QRIS URL dari action generate-qr-code.
         */
        $actions = collect($body['actions'] ?? []);

        $qrAction = $actions->firstWhere(
            'name',
            'generate-qr-code'
        );

        $redirectAction = $actions->firstWhere(
            'name',
            'deeplink-redirect'
        );

        $qrUrl = is_array($qrAction)
            ? ($qrAction['url'] ?? null)
            : null;

        $paymentUrl = is_array($redirectAction)
            ? ($redirectAction['url'] ?? null)
            : ($body['redirect_url'] ?? null);

        $expiresAt = ! empty($body['expiry_time'])
            ? Carbon::parse($body['expiry_time'])
            : now()->addHours(24);

        /*
         * Simpan respons Midtrans.
         */
        return DB::transaction(function () use (
            $attempt,
            $payment,
            $body,
            $qrUrl,
            $paymentUrl,
            $expiresAt
        ): Payment {
            $lockedAttempt = PaymentAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            $attemptUpdates = [
                'gateway_transaction_id' => $body['transaction_id'],
                'qr_code_url' => $qrUrl,
                'payment_url' => $paymentUrl,
                'response_payload' => $body,
                'expires_at' => $expiresAt,
            ];

            if ($lockedAttempt->status === 'creating') {
                $attemptUpdates['status'] = 'pending';
            }

            $lockedAttempt->update($attemptUpdates);

            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $lockedPayment->update([
                'gateway_transaction_id' =>
                    $body['transaction_id']
                    ?? $lockedPayment->gateway_transaction_id,

                // QRIS memakai QR URL, bukan URL lokal PawCare.
                'gateway_payment_url' => $qrUrl ?: $paymentUrl,

                'gateway_expires_at' => $expiresAt,
            ]);

            return $lockedPayment->fresh();
        });
    }

    /**
     * Menandai payment attempt gagal.
     */
    private function markAttemptFailed(
        PaymentAttempt $attempt,
        Payment $payment,
        array $responsePayload
    ): void {
        DB::transaction(function () use (
            $attempt,
            $payment,
            $responsePayload
        ): void {
            $lockedAttempt = PaymentAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            if ($lockedAttempt->status !== 'settlement') {
                $lockedAttempt->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'response_payload' => $responsePayload,
                ]);
            }

            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->status !== Payment::PAID) {
                $lockedPayment->update([
                    'gateway_status' => Payment::GATEWAY_DENY,
                    'status' => Payment::REJECTED,
                    'reject_reason' => 'Transaksi Midtrans gagal dibuat.',
                ]);
            }
        });
    }

    /**
     * Signature resmi Midtrans SHA-512.
     */
    public function generateSignature(
        string $orderId,
        string $statusCode,
        string $grossAmount
    ): string {
        return hash(
            'sha512',
            $orderId . $statusCode . $grossAmount . $this->serverKey()
        );
    }

    public function verifySignature(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $signatureKey
    ): bool {
        if (
            $orderId === '' ||
            $statusCode === '' ||
            $grossAmount === '' ||
            $signatureKey === '' ||
            $this->serverKey() === ''
        ) {
            return false;
        }

        return hash_equals(
            $this->generateSignature(
                $orderId,
                $statusCode,
                $grossAmount
            ),
            $signatureKey
        );
    }

    /**
     * Memproses webhook Midtrans.
     *
     * @param array<string,mixed> $payload
     * @return array{payment:Payment,idempotent:bool}
     */
    public function processWebhook(array $payload): array
    {
        $orderId = trim((string) ($payload['order_id'] ?? ''));
        $statusCode = trim((string) ($payload['status_code'] ?? ''));
        $grossRaw = trim((string) ($payload['gross_amount'] ?? ''));
        $signature = trim((string) ($payload['signature_key'] ?? ''));

        $status = strtolower(
            trim((string) ($payload['transaction_status'] ?? ''))
        );

        $fraudStatus = strtolower(
            trim((string) ($payload['fraud_status'] ?? 'accept'))
        );

        $transactionId = trim(
            (string) ($payload['transaction_id'] ?? '')
        );

        $paymentType = trim(
            (string) ($payload['payment_type'] ?? '')
        );

        if (! is_numeric($grossRaw)) {
            throw ValidationException::withMessages([
                'gross_amount' => 'Nominal webhook tidak valid.',
            ]);
        }

        if (! $this->verifySignature(
            $orderId,
            $statusCode,
            $grossRaw,
            $signature
        )) {
            throw new AccessDeniedHttpException(
                'Signature webhook Midtrans tidak valid.'
            );
        }

        $payment = Payment::with('booking.customer')
            ->where('gateway_reference', $orderId)
            ->first();

        if (! $payment || ! $payment->booking) {
            throw ValidationException::withMessages([
                'order_id' => 'Transaksi pembayaran tidak ditemukan.',
            ]);
        }

        if (
            abs((float) $grossRaw - (float) $payment->amount) > 0.01
        ) {
            throw ValidationException::withMessages([
                'gross_amount' => 'Nominal transaksi webhook tidak sesuai.',
            ]);
        }

        $idempotencyKey = hash('sha256', implode('|', [
            $orderId,
            $status,
            $statusCode,
            $transactionId,
            (string) ($payload['refund_key'] ?? ''),
            (string) ($payload['refund_amount'] ?? ''),
        ]));

        return DB::transaction(function () use (
            $payment,
            $payload,
            $orderId,
            $statusCode,
            $grossRaw,
            $status,
            $fraudStatus,
            $transactionId,
            $paymentType,
            $idempotencyKey
        ): array {
            $lockedPayment = Payment::with('booking.customer')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->gateway_reference !== $orderId) {
                throw ValidationException::withMessages([
                    'order_id' => 'Referensi pembayaran tidak cocok.',
                ]);
            }

            if (
                abs(
                    (float) $grossRaw -
                    (float) $lockedPayment->amount
                ) > 0.01
            ) {
                throw ValidationException::withMessages([
                    'gross_amount' => 'Nominal webhook tidak sesuai.',
                ]);
            }

            /*
             * Idempotency:
             * webhook yang sama tidak boleh membuat ledger ganda.
             */
            if (
                PaymentGatewayLog::where(
                    'idempotency_key',
                    $idempotencyKey
                )->exists()
            ) {
                // Pemulihan jika transaksi lama sudah PAID
                // tetapi settlement belum tercatat.
                if (
                    in_array($status, ['settlement', 'capture'], true) &&
                    $fraudStatus === 'accept' &&
                    $lockedPayment->status === Payment::PAID
                ) {
                    app(PaymentFinancialService::class)
                        ->settle($lockedPayment->fresh());
                }

                return [
                    'payment' => $lockedPayment->fresh(),
                    'idempotent' => true,
                ];
            }

            /*
             * Audit payload hanya menyimpan field yang diperlukan.
             */
            $allowedKeys = [
                'order_id',
                'status_code',
                'gross_amount',
                'transaction_status',
                'fraud_status',
                'transaction_id',
                'payment_type',
                'transaction_time',
                'settlement_time',
                'currency',
                'refund_key',
                'refund_amount',
            ];

            $safePayload = array_intersect_key(
                $payload,
                array_flip($allowedKeys)
            );

            PaymentGatewayLog::create([
                'payment_id' => $lockedPayment->id,
                'idempotency_key' => $idempotencyKey,
                'provider' => self::PROVIDER,
                'order_id' => $orderId,
                'transaction_status' => $status,
                'status_code' => $statusCode,
                'gross_amount' => (float) $grossRaw,
                'payload' => json_encode(
                    $safePayload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'processed_at' => now(),
            ]);

            /*
             * Penanganan refund.
             */
            if (in_array($status, ['refund', 'partial_refund'], true)) {
                $refundQuery = PaymentRefund::query()
                    ->where('payment_id', $lockedPayment->id)
                    ->whereIn('status', ['pending', 'requested']);

                if (filled($payload['refund_key'] ?? null)) {
                    $refundQuery->where(
                        'provider_reference',
                        (string) $payload['refund_key']
                    );
                }

                $refund = $refundQuery->oldest('id')->first();

                if ($refund) {
                    app(PaymentFinancialService::class)
                        ->completeRefund($refund, $payload);
                }
            }

            $booking = $lockedPayment->booking;
            $customer = $booking?->customer;

            /*
             * Settlement atau capture berhasil.
             */
            $isSuccessful = $status === 'settlement'
                || ($status === 'capture' && $fraudStatus === 'accept');

            if ($isSuccessful) {
                $wasAlreadyPaid = $lockedPayment->status === Payment::PAID;

                if (! $wasAlreadyPaid) {
                    $lockedPayment->update([
                        'payment_method' => Payment::METHOD_GATEWAY,
                        'gateway_status' => Payment::GATEWAY_SETTLEMENT,
                        'gateway_transaction_id' => $transactionId
                            ?: $lockedPayment->gateway_transaction_id,
                        'gateway_payment_type' => $paymentType
                            ?: $lockedPayment->gateway_payment_type,
                        'status' => Payment::PAID,
                        'paid_at' => now(),
                        'verified_at' => now(),
                        'reject_reason' => null,
                    ]);
                }

                PaymentAttempt::query()
                    ->where('gateway_order_id', $orderId)
                    ->update([
                        'status' => 'settlement',
                        'gateway_transaction_id' => $transactionId ?: null,
                        'settled_at' => now(),
                    ]);

                /*
                 * INI BAGIAN PENTING:
                 * Buat settlement dan double-entry ledger.
                 * PaymentFinancialService sudah memiliki idempotency.
                 */
                app(PaymentFinancialService::class)
                    ->settle($lockedPayment->fresh());

                if (! $wasAlreadyPaid && $booking && $customer) {
                    $customer->notify(
                        new PawCareStatusNotification(
                            title: 'Pembayaran berhasil (Gateway)',
                            message: "Pembayaran untuk booking {$booking->invoice_number} telah diterima melalui Midtrans.",
                            url: route(
                                'customer.bookings.show',
                                $booking->id
                            )
                        )
                    );
                }
            } elseif ($status === 'pending') {
                if ($lockedPayment->status !== Payment::PAID) {
                    $lockedPayment->update([
                        'gateway_status' => Payment::GATEWAY_PENDING,
                        'gateway_transaction_id' => $transactionId
                            ?: $lockedPayment->gateway_transaction_id,
                    ]);

                    PaymentAttempt::query()
                        ->where('gateway_order_id', $orderId)
                        ->where('status', 'creating')
                        ->update([
                            'status' => 'pending',
                            'gateway_transaction_id' => $transactionId ?: null,
                        ]);
                }
            } elseif (in_array(
                $status,
                ['expire', 'cancel', 'deny', 'failure'],
                true
            )) {
                // Jangan pernah menurunkan status transaksi yang sudah lunas.
                if ($lockedPayment->status !== Payment::PAID) {
                    $gatewayState = match ($status) {
                        'expire' => Payment::GATEWAY_EXPIRE,
                        'cancel' => Payment::GATEWAY_CANCEL,
                        default => Payment::GATEWAY_DENY,
                    };

                    $reason = match ($status) {
                        'expire' => 'Sesi pembayaran Midtrans telah kedaluwarsa.',
                        'cancel' => 'Transaksi Midtrans dibatalkan.',
                        default => 'Pembayaran ditolak atau gagal diproses Midtrans.',
                    };

                    $lockedPayment->update([
                        'gateway_status' => $gatewayState,
                        'gateway_transaction_id' => $transactionId
                            ?: $lockedPayment->gateway_transaction_id,
                        'status' => Payment::REJECTED,
                        'reject_reason' => $reason,
                        'paid_at' => null,
                    ]);

                    PaymentAttempt::query()
                        ->where('gateway_order_id', $orderId)
                        ->update([
                            'status' => $status,
                            'gateway_transaction_id' => $transactionId ?: null,
                            'failed_at' => now(),
                        ]);

                    if ($booking && $customer) {
                        $customer->notify(
                            new PawCareStatusNotification(
                                title: 'Pembayaran tidak berhasil',
                                message: "Pembayaran untuk booking {$booking->invoice_number} belum berhasil. {$reason}",
                                url: route(
                                    'customer.bookings.pay',
                                    $booking->id
                                )
                            )
                        );
                    }
                }
            }

            return [
                'payment' => $lockedPayment->fresh(),
                'idempotent' => false,
            ];
        });
    }
}
