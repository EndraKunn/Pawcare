<?php

namespace App\Services;

use App\Models\Business;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentReconciliationRun;
use App\Models\PaymentRefund;
use App\Models\PaymentSettlement;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentFinancialService
{
    /**
     * Mengubah nominal Rupiah menjadi integer tanpa perhitungan float.
     * Database tetap menggunakan decimal(12,2) dan decimal(14,2).
     */
    private function rupiahToInt(mixed $value, string $field = 'amount'): int
    {
        if (is_float($value)) {
            if (!is_finite($value) || floor($value) !== $value) {
                throw ValidationException::withMessages([
                    $field => 'Nominal Rupiah harus berupa bilangan bulat.',
                ]);
            }

            $value = sprintf('%.0f', $value);
        }

        $value = (string) $value;

        if (!preg_match('/^\d+(?:\.0{1,2})?$/D', $value)) {
            throw ValidationException::withMessages([
                $field => 'Nominal Rupiah harus berupa bilangan bulat positif.',
            ]);
        }

        return (int) explode('.', $value)[0];
    }

    /**
     * Saldo tersedia = kredit - debit - saldo yang masih direservasi.
     */
    public function availableBalance(int $businessId): int
    {
        $credits = $this->rupiahToInt(
            LedgerEntry::where('business_id', $businessId)
                ->where('account_code', 'business_payable')
                ->sum('credit')
        );

        $debits = $this->rupiahToInt(
            LedgerEntry::where('business_id', $businessId)
                ->where('account_code', 'business_payable')
                ->sum('debit')
        );

        $reserved = $this->rupiahToInt(
            Withdrawal::where('business_id', $businessId)
                ->whereIn('status', [
                    Withdrawal::REQUESTED,
                    Withdrawal::APPROVED,
                ])
                ->sum('amount')
        );

        return $credits - $debits - $reserved;
    }

    /**
     * Settlement pembayaran dan pencatatan ledger.
     */
    public function settle(Payment $payment): PaymentSettlement
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::with('booking.business')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (
                $payment->status !== Payment::PAID
                || !$payment->booking?->business_id
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran belum lunas atau bisnis tidak ditemukan.',
                ]);
            }

            $existing = PaymentSettlement::where(
                'payment_id',
                $payment->id
            )->first();

            if ($existing) {
                return $existing;
            }

            $businessId = (int) $payment->booking->business_id;

            Business::whereKey($businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $gross = $this->rupiahToInt(
                $payment->getRawOriginal('amount')
            );

            if ($gross <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Nominal pembayaran tidak valid.',
                ]);
            }

            $feePercent = max(
                0,
                min(
                    100,
                    (float) config(
                        'services.pawcare.platform_fee_percent',
                        10
                    )
                )
            );

            $fee = (int) round($gross * $feePercent / 100);
            $net = $gross - $fee;

            $settlement = PaymentSettlement::create([
                'payment_id' => $payment->id,
                'business_id' => $businessId,
                'reference' => 'SET-' . Str::upper(Str::random(18)),
                'gross_amount' => $gross,
                'platform_fee' => $fee,
                'net_amount' => $net,
                'source' => $payment->gateway_provider ?: 'manual',
                'status' => 'settled',
                'settled_at' => now(),
            ]);

            $group = (string) Str::uuid();

            LedgerEntry::insert([
                [
                    'entry_group_id' => $group,
                    'business_id' => null,
                    'account_code' => 'gateway_clearing',
                    'debit' => $gross,
                    'credit' => 0,
                    'source_type' => 'payment_settlement',
                    'source_id' => $settlement->id,
                    'idempotency_key' => 'settlement:' . $payment->id . ':clearing',
                    'description' => 'Dana masuk dari pembayaran',
                    'created_at' => now(),
                ],
                [
                    'entry_group_id' => $group,
                    'business_id' => null,
                    'account_code' => 'platform_revenue',
                    'debit' => 0,
                    'credit' => $fee,
                    'source_type' => 'payment_settlement',
                    'source_id' => $settlement->id,
                    'idempotency_key' => 'settlement:' . $payment->id . ':fee',
                    'description' => 'Biaya layanan platform',
                    'created_at' => now(),
                ],
                [
                    'entry_group_id' => $group,
                    'business_id' => $businessId,
                    'account_code' => 'business_payable',
                    'debit' => 0,
                    'credit' => $net,
                    'source_type' => 'payment_settlement',
                    'source_id' => $settlement->id,
                    'idempotency_key' => 'settlement:' . $payment->id . ':business',
                    'description' => 'Saldo bersih bisnis',
                    'created_at' => now(),
                ],
            ]);

            return $settlement;
        });
    }

    /**
     * Pengajuan withdrawal dengan penguncian bisnis dan idempotency.
     */
    public function requestWithdrawal(
        int $businessId,
        int $userId,
        array $data
    ): Withdrawal {
        $amount = $this->rupiahToInt($data['amount'] ?? null);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal withdrawal harus lebih dari nol.',
            ]);
        }

        $key = (string) ($data['idempotency_key'] ?? '');

        if (!Str::isUuid($key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key tidak valid.',
            ]);
        }

        return DB::transaction(function () use (
            $businessId,
            $userId,
            $amount,
            $data,
            $key
        ) {
            Business::whereKey($businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Withdrawal::where(
                'idempotency_key',
                $key
            )->first();

            if ($existing) {
                if (
                    (int) $existing->business_id !== $businessId
                    || (int) $existing->requested_by !== $userId
                    || $this->rupiahToInt($existing->getRawOriginal('amount')) !== $amount
                ) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Idempotency key sudah digunakan untuk transaksi lain.',
                    ]);
                }

                return $existing;
            }

            if ($amount > $this->availableBalance($businessId)) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo tersedia tidak mencukupi untuk withdrawal.',
                ]);
            }

            return Withdrawal::create([
                'business_id' => $businessId,
                'requested_by' => $userId,
                'amount' => $amount,
                'bank_name' => $data['bank_name'],
                'bank_account_number' => $data['bank_account_number'],
                'bank_account_name' => $data['bank_account_name'],
                'status' => Withdrawal::REQUESTED,
                'idempotency_key' => $key,
            ]);
        });
    }

    /**
     * Persetujuan withdrawal.
     */
    public function approveWithdrawal(
        Withdrawal $withdrawal,
        int $adminId
    ): Withdrawal {
        return DB::transaction(function () use ($withdrawal, $adminId) {
            $businessId = (int) $withdrawal->business_id;

            Business::whereKey($businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $w = Withdrawal::lockForUpdate()
                ->findOrFail($withdrawal->id);

            if ($w->status !== Withdrawal::REQUESTED) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Status penarikan bukan requested.',
                ]);
            }

            // Withdrawal requested sudah termasuk reservasi saldo.
            if ($this->availableBalance($businessId) < 0) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Saldo bisnis tidak mencukupi setelah memperhitungkan reservasi.',
                ]);
            }

            $w->update([
                'status' => Withdrawal::APPROVED,
                'approved_by' => $adminId,
            ]);

            return $w->fresh();
        });
    }

    /**
     * Penolakan withdrawal.
     */
    public function rejectWithdrawal(
        Withdrawal $withdrawal,
        int $adminId,
        string $reason
    ): Withdrawal {
        return DB::transaction(function () use (
            $withdrawal,
            $adminId,
            $reason
        ) {
            $businessId = (int) $withdrawal->business_id;

            Business::whereKey($businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $w = Withdrawal::lockForUpdate()
                ->findOrFail($withdrawal->id);

            if ($w->status !== Withdrawal::REQUESTED) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Penarikan tidak dapat ditolak pada status ini.',
                ]);
            }

            $w->update([
                'status' => Withdrawal::REJECTED,
                'approved_by' => $adminId,
                'rejection_reason' => $reason,
            ]);

            return $w->fresh();
        });
    }

    /**
     * Menandai withdrawal telah dibayar dan membuat ledger.
     */
    public function markWithdrawalPaid(
        Withdrawal $withdrawal,
        int $adminId,
        string $reference
    ): Withdrawal {
        return DB::transaction(function () use (
            $withdrawal,
            $adminId,
            $reference
        ) {
            $businessId = (int) $withdrawal->business_id;

            Business::whereKey($businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $w = Withdrawal::lockForUpdate()
                ->findOrFail($withdrawal->id);

            if ($w->status !== Withdrawal::APPROVED) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Penarikan harus disetujui sebelum dibayar.',
                ]);
            }

            if ($this->availableBalance($businessId) < 0) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Saldo tidak mencukupi untuk pembayaran withdrawal.',
                ]);
            }

            $amount = $this->rupiahToInt(
                $w->getRawOriginal('amount')
            );

            $w->update([
                'status' => Withdrawal::PAID,
                'processed_by' => $adminId,
                'reference' => $reference,
                'processed_at' => now(),
            ]);

            $group = (string) Str::uuid();

            LedgerEntry::insert([
                [
                    'entry_group_id' => $group,
                    'business_id' => $businessId,
                    'account_code' => 'business_payable',
                    'debit' => $amount,
                    'credit' => 0,
                    'source_type' => 'withdrawal',
                    'source_id' => $w->id,
                    'idempotency_key' => 'withdrawal:' . $w->id . ':business',
                    'description' => 'Penarikan saldo bisnis',
                    'created_at' => now(),
                ],
                [
                    'entry_group_id' => $group,
                    'business_id' => null,
                    'account_code' => 'cash_clearing',
                    'debit' => 0,
                    'credit' => $amount,
                    'source_type' => 'withdrawal',
                    'source_id' => $w->id,
                    'idempotency_key' => 'withdrawal:' . $w->id . ':cash',
                    'description' => 'Dana keluar untuk withdrawal',
                    'created_at' => now(),
                ],
            ]);

            return $w->fresh();
        });
    }

    /**
     * Pengajuan refund dengan locking dan idempotency.
     *
     * HTTP Midtrans dijalankan di luar transaksi database.
     */
    public function requestRefund(
        Payment $payment,
        int $adminId,
        int $amount,
        string $reason,
        string $idempotencyKey
    ): PaymentRefund {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal refund harus lebih dari nol.',
            ]);
        }

        if (!Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key tidak valid.',
            ]);
        }

        $result = DB::transaction(function () use (
            $payment,
            $adminId,
            $amount,
            $reason,
            $idempotencyKey
        ) {
            $lockedPayment = Payment::with('booking.business')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $existing = PaymentRefund::where(
                'idempotency_key',
                $idempotencyKey
            )->first();

            if ($existing) {
                if (
                    (int) $existing->payment_id !== (int) $lockedPayment->id
                    || (int) $existing->requested_by !== $adminId
                    || $this->rupiahToInt($existing->getRawOriginal('amount')) !== $amount
                ) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Idempotency key sudah digunakan untuk transaksi lain.',
                    ]);
                }

                return [
                    'refund' => $existing,
                    'created' => false,
                    'payment' => $lockedPayment,
                ];
            }

            if ($lockedPayment->status !== Payment::PAID) {
                throw ValidationException::withMessages([
                    'payment' => 'Refund hanya untuk pembayaran lunas.',
                ]);
            }

            $businessId = (int) $lockedPayment->booking?->business_id;

            if (!$businessId) {
                throw ValidationException::withMessages([
                    'payment' => 'Bisnis dari pembayaran tidak ditemukan.',
                ]);
            }

            Business::whereKey($businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $already = $this->rupiahToInt(
                PaymentRefund::where('payment_id', $lockedPayment->id)
                    ->whereIn('status', [
                        'requested',
                        'pending',
                        'succeeded',
                    ])
                    ->sum('amount')
            );

            $paidAmount = $this->rupiahToInt(
                $lockedPayment->getRawOriginal('amount')
            );

            if ($amount + $already > $paidAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal refund melebihi sisa pembayaran.',
                ]);
            }

            $refund = PaymentRefund::create([
                'payment_id' => $lockedPayment->id,
                'requested_by' => $adminId,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'requested',
                'idempotency_key' => $idempotencyKey,
            ]);

            return [
                'refund' => $refund,
                'created' => true,
                'payment' => $lockedPayment,
            ];
        });

        $refund = $result['refund'];
        $lockedPayment = $result['payment'];

        $isMidtrans = (
            $lockedPayment->gateway_provider === 'midtrans'
            && !empty($lockedPayment->gateway_reference)
        );

        // Refund baru dikirim, atau refund lama berstatus requested
        // dicoba kembali dengan refund_key yang sama.
        $shouldSubmit = $result['created']
            || (
                !$result['created']
                && $refund->status === 'requested'
                && $isMidtrans
            );

        if (!$shouldSubmit || !$isMidtrans) {
            return $refund;
        }

        if (
            $lockedPayment->gateway_provider === 'midtrans'
            && $lockedPayment->gateway_reference
        ) {
            $key = (string) config('services.midtrans.server_key');

            if ($key === '') {
                throw ValidationException::withMessages([
                    'midtrans' => 'MIDTRANS_SERVER_KEY belum diatur.',
                ]);
            }

            $base = config('services.midtrans.is_production')
                ? 'https://api.midtrans.com'
                : 'https://api.sandbox.midtrans.com';

            // Reference stabil agar retry manual tidak membuat refund key baru.
            $refundKey = 'PAW-REF-' . $refund->id;

            $refund->update([
                'provider_reference' => $refundKey,
            ]);

            try {
                $response = Http::withBasicAuth($key, '')
                    ->acceptJson()
                    ->asJson()
                    ->timeout(20)
                    ->post(
                        $base . '/v2/' . rawurlencode(
                            $lockedPayment->gateway_reference
                        ) . '/refund',
                        [
                            'refund_key' => $refundKey,
                            'amount' => $amount,
                            'reason' => $reason,
                        ]
                    );

                $refund->update([
                    'status' => $response->successful()
                        ? 'pending'
                        : ($response->clientError() ? 'failed' : 'requested'),
                    'response_payload' => $response->json(),
                ]);

                if (!$response->successful()) {
                    throw ValidationException::withMessages([
                        'refund' => 'Midtrans belum menerima permintaan refund. Periksa status provider sebelum mencoba ulang.',
                    ]);
                }
            } catch (ValidationException $e) {
                throw $e;
            } catch (Throwable $e) {
                // Timeout tidak otomatis berarti provider menolak transaksi.
                // Status tetap requested untuk pemeriksaan dan rekonsiliasi.
                throw ValidationException::withMessages([
                    'refund' => 'Respons Midtrans tidak dapat dipastikan. Periksa status refund provider sebelum melakukan tindakan ulang.',
                ]);
            }
        }

        return $refund->fresh();
    }

    /**
     * Rekonsiliasi status transaksi Midtrans.
     */
    public function reconcile(int $adminId): PaymentReconciliationRun
    {
        $key = (string) config('services.midtrans.server_key');

        if ($key === '') {
            throw ValidationException::withMessages([
                'midtrans' => 'MIDTRANS_SERVER_KEY belum diatur.',
            ]);
        }

        $run = PaymentReconciliationRun::create([
            'provider' => 'midtrans',
            'started_by' => $adminId,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $details = [];
        $checked = 0;
        $matched = 0;
        $mismatch = 0;

        $payments = Payment::where('gateway_provider', 'midtrans')
            ->whereNotNull('gateway_reference')
            ->whereIn('gateway_status', ['pending', 'settlement'])
            ->limit(200)
            ->get();

        $base = config('services.midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        foreach ($payments as $payment) {
            $checked++;

            try {
                $response = Http::withBasicAuth($key, '')
                    ->acceptJson()
                    ->timeout(12)
                    ->get(
                        $base . '/v2/' . rawurlencode(
                            $payment->gateway_reference
                        ) . '/status'
                    );

                $remote = $response->json('transaction_status');

                $normalized = match ((string) $remote) {
                    'capture', 'settlement' => 'settlement',
                    'pending' => 'pending',
                    'expire' => 'expire',
                    'cancel' => 'cancel',
                    'deny' => 'deny',
                    default => (string) $remote,
                };

                $same = $response->successful()
                    && $normalized === (string) $payment->gateway_status;

                if ($same) {
                    $matched++;
                } else {
                    $mismatch++;
                }

                $details[] = [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->gateway_reference,
                    'local_status' => $payment->gateway_status,
                    'remote_status' => $remote,
                    'matched' => $same,
                    'http_status' => $response->status(),
                ];
            } catch (Throwable $e) {
                $mismatch++;

                $details[] = [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->gateway_reference,
                    'error' => 'Provider status unavailable',
                ];
            }
        }

        $run->update([
            'status' => 'completed',
            'checked_count' => $checked,
            'matched_count' => $matched,
            'mismatch_count' => $mismatch,
            'details' => $details,
            'finished_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Penyelesaian refund dari webhook provider atau proses manual.
     */
    public function completeRefund(
        PaymentRefund $refund,
        array $providerPayload = []
    ): PaymentRefund {
        return DB::transaction(function () use (
            $refund,
            $providerPayload
        ) {
            // Urutan locking: payment, refund, business.
            $paymentId = (int) $refund->payment_id;

            $payment = Payment::with('booking.business')
                ->lockForUpdate()
                ->findOrFail($paymentId);

            $r = PaymentRefund::lockForUpdate()
                ->findOrFail($refund->id);

            if ($r->status === 'succeeded') {
                return $r;
            }

            if (!in_array($r->status, ['requested', 'pending'], true)) {
                throw ValidationException::withMessages([
                    'refund' => 'Status refund tidak dapat diselesaikan.',
                ]);
            }

            $providerAmount = $providerPayload['refund_amount']
                ?? $providerPayload['amount']
                ?? null;

            $refundAmount = $this->rupiahToInt(
                $r->getRawOriginal('amount')
            );

            if ($providerAmount !== null) {
                $remoteAmount = $this->rupiahToInt($providerAmount);

                if ($remoteAmount !== $refundAmount) {
                    throw ValidationException::withMessages([
                        'refund_amount' => 'Nominal refund dari provider tidak sesuai permintaan.',
                    ]);
                }
            }

            $settlement = PaymentSettlement::where(
                'payment_id',
                $payment->id
            )->first();

            if ($settlement) {
                Business::whereKey($settlement->business_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $r->update([
                'status' => 'succeeded',
                'response_payload' => $providerPayload,
                'processed_at' => now(),
            ]);

            if ($settlement) {
                $gross = $refundAmount;

                $settlementGross = $this->rupiahToInt(
                    $settlement->getRawOriginal('gross_amount')
                );

                $settlementFee = $this->rupiahToInt(
                    $settlement->getRawOriginal('platform_fee')
                );

                if ($settlementGross <= 0) {
                    throw ValidationException::withMessages([
                        'settlement' => 'Nominal settlement tidak valid.',
                    ]);
                }

                $fee = (int) round(
                    $settlementFee * $gross / $settlementGross
                );

                $net = $gross - $fee;
                $group = (string) Str::uuid();

                LedgerEntry::insert([
                    [
                        'entry_group_id' => $group,
                        'business_id' => $settlement->business_id,
                        'account_code' => 'business_payable',
                        'debit' => $net,
                        'credit' => 0,
                        'source_type' => 'refund',
                        'source_id' => $r->id,
                        'idempotency_key' => 'refund:' . $r->id . ':business',
                        'description' => 'Pembalikan saldo bisnis karena refund',
                        'created_at' => now(),
                    ],
                    [
                        'entry_group_id' => $group,
                        'business_id' => null,
                        'account_code' => 'platform_revenue',
                        'debit' => $fee,
                        'credit' => 0,
                        'source_type' => 'refund',
                        'source_id' => $r->id,
                        'idempotency_key' => 'refund:' . $r->id . ':fee',
                        'description' => 'Pembalikan biaya platform karena refund',
                        'created_at' => now(),
                    ],
                    [
                        'entry_group_id' => $group,
                        'business_id' => null,
                        'account_code' => 'gateway_clearing',
                        'debit' => 0,
                        'credit' => $gross,
                        'source_type' => 'refund',
                        'source_id' => $r->id,
                        'idempotency_key' => 'refund:' . $r->id . ':clearing',
                        'description' => 'Dana keluar untuk refund',
                        'created_at' => now(),
                    ],
                ]);
            }

            return $r->fresh();
        });
    }

    /**
     * Penyelesaian refund manual.
     */
    public function completeManualRefund(
        PaymentRefund $refund,
        int $adminId,
        string $reference
    ): PaymentRefund {
        return DB::transaction(function () use (
            $refund,
            $adminId,
            $reference
        ) {
            $payment = Payment::lockForUpdate()
                ->findOrFail($refund->payment_id);

            $r = PaymentRefund::lockForUpdate()
                ->findOrFail($refund->id);

            if ($payment->gateway_provider === 'midtrans') {
                throw ValidationException::withMessages([
                    'refund' => 'Refund Midtrans harus dikonfirmasi melalui webhook provider.',
                ]);
            }

            if ($r->status !== 'requested') {
                throw ValidationException::withMessages([
                    'refund' => 'Refund bukan berstatus requested.',
                ]);
            }

            $r->update([
                'provider_reference' => $reference,
            ]);

            return $this->completeRefund(
                $r->fresh(),
                [
                    'processed_by' => $adminId,
                    'reference' => $reference,
                    'channel' => 'manual',
                ]
            );
        });
    }
}