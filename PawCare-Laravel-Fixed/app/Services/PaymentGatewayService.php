<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentGatewayLog;
use App\Models\User;
use App\Notifications\PawCareStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaymentGatewayService
{
    public const PROVIDER = 'midtrans';

    public const CHANNELS = [
        'qris' => 'QRIS (GoPay, OVO, Dana, ShopeePay, Mobile Banking)',
        'bca_va' => 'BCA Virtual Account',
        'bri_va' => 'BRI Virtual Account',
        'mandiri_va' => 'Mandiri Bill Payment',
        'bni_va' => 'BNI Virtual Account',
    ];

    public function serverKey(): string
    {
        return (string) config('services.midtrans.server_key', 'SB-Mid-server-pawcare-sandbox-key');
    }

    /**
     * Buat atau perbarui sesi pembayaran Payment Gateway (Midtrans Sandbox)
     * menggunakan nominal resmi dari database.
     */
    public function createTransaction(
        Booking $booking,
        User $customer,
        string $channel = 'qris'
    ): Payment {
        if ((int) $booking->customer_id !== (int) $customer->id) {
            throw new AccessDeniedHttpException('Anda tidak berhak mengakses pembayaran booking ini.');
        }

        if ($booking->status === Booking::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'payment' => 'Booking yang dibatalkan tidak dapat dibayar.',
            ]);
        }

        $payment = $booking->payment;
        if (! $payment) {
            throw ValidationException::withMessages([
                'payment' => 'Data pembayaran tidak ditemukan.',
            ]);
        }

        if ($payment->status === Payment::PAID) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran untuk booking ini sudah lunas.',
            ]);
        }

        if (! array_key_exists($channel, self::CHANNELS)) {
            $channel = 'qris';
        }

        // Gunakan nominal dari database (total_price booking), bukan input pengguna.
        $grossAmount = (float) $booking->total_price;
        $orderId = 'PAW-'.$booking->id.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
        $transactionId = 'MID-SBX-'.strtoupper(Str::random(12));
        $expiresAt = now()->addHours(24);
        $paymentUrl = route('customer.bookings.pay', ['booking' => $booking->id, 'gateway_ref' => $orderId]);

        // Jika diaktifkan koneksi ke endpoint Midtrans Sandbox eksternal:
        if (config('services.midtrans.use_remote_sandbox') && filled(config('services.midtrans.server_key'))) {
            try {
                $response = Http::withBasicAuth($this->serverKey(), '')
                    ->timeout(8)
                    ->acceptJson()
                    ->post((string) config('services.midtrans.snap_url'), [
                        'transaction_details' => [
                            'order_id' => $orderId,
                            'gross_amount' => (int) round($grossAmount),
                        ],
                        'customer_details' => [
                            'first_name' => $customer->name,
                            'email' => $customer->email,
                            'phone' => $customer->phone,
                        ],
                    ]);

                if ($response->successful() && $response->json('redirect_url')) {
                    $paymentUrl = (string) $response->json('redirect_url');
                    $transactionId = (string) ($response->json('token') ?: $transactionId);
                }
            } catch (\Throwable) {
                // Tetap lanjutkan dengan sandbox internal jika jaringan offline.
            }
        }

        $payment->update([
            'amount' => $grossAmount,
            'payment_method' => Payment::METHOD_GATEWAY,
            'gateway_provider' => self::PROVIDER,
            'gateway_reference' => $orderId,
            'gateway_transaction_id' => $transactionId,
            'gateway_payment_type' => $channel,
            'gateway_status' => Payment::GATEWAY_PENDING,
            'gateway_payment_url' => $paymentUrl,
            'gateway_expires_at' => $expiresAt,
            'reject_reason' => null,
        ]);

        return $payment->fresh();
    }

    /**
     * Hitung signature resmi Midtrans: SHA512(order_id + status_code + gross_amount + ServerKey).
     */
    public function generateSignature(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey());
    }

    public function verifySignature(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $signatureKey
    ): bool {
        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            return false;
        }

        $expected = $this->generateSignature($orderId, $statusCode, $grossAmount);

        return hash_equals($expected, $signatureKey);
    }

    /**
     * Memproses webhook callback Payment Gateway dengan verifikasi signature,
     * validasi nominal, dan idempotensi penuh.
     *
     * @param  array<string,mixed>  $payload
     * @return array{payment:Payment,idempotent:bool}
     */
    public function processWebhook(array $payload): array
    {
        $orderId = trim((string) ($payload['order_id'] ?? ''));
        $statusCode = trim((string) ($payload['status_code'] ?? '200'));
        $grossAmountRaw = trim((string) ($payload['gross_amount'] ?? ''));
        $signatureKey = trim((string) ($payload['signature_key'] ?? ''));
        $transactionStatus = strtolower(trim((string) ($payload['transaction_status'] ?? '')));
        $fraudStatus = strtolower(trim((string) ($payload['fraud_status'] ?? 'accept')));
        $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
        $paymentType = trim((string) ($payload['payment_type'] ?? ''));

        if (! $this->verifySignature($orderId, $statusCode, $grossAmountRaw, $signatureKey)) {
            throw new AccessDeniedHttpException('Signature webhook tidak valid.');
        }

        $payment = Payment::with('booking.customer')
            ->where('gateway_reference', $orderId)
            ->first();

        if (! $payment || ! $payment->booking) {
            throw ValidationException::withMessages([
                'order_id' => 'Transaksi pembayaran tidak ditemukan.',
            ]);
        }

        $grossAmount = (float) $grossAmountRaw;
        if (abs($grossAmount - (float) $payment->amount) > 0.01) {
            throw ValidationException::withMessages([
                'gross_amount' => 'Nominal transaksi webhook tidak sesuai dengan tagihan di server.',
            ]);
        }

        // Idempotency key unik untuk kombinasi order_id + transaction_status + status_code
        $idempotencyKey = hash('sha256', "{$orderId}|{$transactionStatus}|{$statusCode}");

        if (PaymentGatewayLog::where('idempotency_key', $idempotencyKey)->exists()) {
            return [
                'payment' => $payment,
                'idempotent' => true,
            ];
        }

        return DB::transaction(function () use (
            $payment,
            $idempotencyKey,
            $orderId,
            $transactionStatus,
            $fraudStatus,
            $statusCode,
            $grossAmount,
            $transactionId,
            $paymentType,
            $payload
        ) {
            // Double-check idempotensi di dalam transaksi
            if (PaymentGatewayLog::where('idempotency_key', $idempotencyKey)->exists()) {
                return [
                    'payment' => $payment->fresh(),
                    'idempotent' => true,
                ];
            }

            // Simpan log tanpa menyertakan signature_key / secret
            $safePayload = $payload;
            unset($safePayload['signature_key']);

            PaymentGatewayLog::create([
                'payment_id' => $payment->id,
                'idempotency_key' => $idempotencyKey,
                'provider' => self::PROVIDER,
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'status_code' => $statusCode,
                'gross_amount' => $grossAmount,
                'payload' => json_encode($safePayload, JSON_UNESCAPED_UNICODE),
                'processed_at' => now(),
            ]);

            // Jika pembayaran sudah Dibayar sebelumnya, jangan timpa mundur ke gagal/expire
            if ($payment->status === Payment::PAID) {
                return [
                    'payment' => $payment,
                    'idempotent' => true,
                ];
            }

            $booking = $payment->booking;
            $customer = $booking?->customer;

            if (
                $transactionStatus === 'settlement'
                || ($transactionStatus === 'capture' && $fraudStatus === 'accept')
            ) {
                $payment->update([
                    'payment_method' => Payment::METHOD_GATEWAY,
                    'gateway_status' => Payment::GATEWAY_SETTLEMENT,
                    'gateway_transaction_id' => $transactionId ?: $payment->gateway_transaction_id,
                    'gateway_payment_type' => $paymentType ?: $payment->gateway_payment_type,
                    'status' => Payment::PAID,
                    'paid_at' => now(),
                    'verified_at' => now(),
                    'reject_reason' => null,
                ]);

                if ($booking && $customer) {
                    $customer->notify(new PawCareStatusNotification(
                        title: 'Pembayaran berhasil (Gateway)',
                        message: "Pembayaran untuk booking {$booking->invoice_number} telah diterima melalui Payment Gateway dan berstatus ".Payment::PAID.'.',
                        url: route('customer.bookings.show', $booking->id),
                    ));
                }
            } elseif ($transactionStatus === 'pending') {
                $payment->update([
                    'gateway_status' => Payment::GATEWAY_PENDING,
                    'gateway_transaction_id' => $transactionId ?: $payment->gateway_transaction_id,
                ]);
            } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny', 'failure'], true)) {
                $gatewayState = match ($transactionStatus) {
                    'expire' => Payment::GATEWAY_EXPIRE,
                    'cancel' => Payment::GATEWAY_CANCEL,
                    default => Payment::GATEWAY_DENY,
                };

                $reasonText = match ($gatewayState) {
                    Payment::GATEWAY_EXPIRE => 'Sesi pembayaran Payment Gateway telah kedaluwarsa.',
                    Payment::GATEWAY_CANCEL => 'Transaksi Payment Gateway dibatalkan.',
                    default => 'Pembayaran ditolak atau gagal diproses oleh Payment Gateway.',
                };

                $payment->update([
                    'gateway_status' => $gatewayState,
                    'gateway_transaction_id' => $transactionId ?: $payment->gateway_transaction_id,
                    'status' => Payment::REJECTED,
                    'reject_reason' => $reasonText,
                    'paid_at' => null,
                ]);

                if ($booking && $customer) {
                    $customer->notify(new PawCareStatusNotification(
                        title: 'Pembayaran tidak berhasil',
                        message: "Pembayaran untuk booking {$booking->invoice_number} belum berhasil. {$reasonText}",
                        url: route('customer.bookings.pay', $booking->id),
                    ));
                }
            }

            return [
                'payment' => $payment->fresh(),
                'idempotent' => false,
            ];
        });
    }
}
