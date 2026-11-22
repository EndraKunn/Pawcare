<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentSettlement;
use App\Models\PaymentAttempt;
use App\Models\Withdrawal;
use App\Models\LedgerEntry;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\PaymentGatewayService;
use App\Services\PaymentFinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class TitahPaymentSettlementTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    /**
     * Settlement membuat ledger seimbang dan idempotent.
     */
    public function test_settlement_membuat_ledger_balanced_dan_idempotent(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000);

        $booking = $this->makeBooking(
            $customer,
            $business,
            $pet,
            [$service->id => 1]
        );

        $payment = $booking->payment;

        $payment->update([
            'status' => Payment::PAID,
            'paid_at' => now(),
        ]);

        $finance = app(PaymentFinancialService::class);

        $first = $finance->settle($payment->fresh());
        $second = $finance->settle($payment->fresh());

        $this->assertSame($first->id, $second->id);

        $this->assertSame(
            1,
            PaymentSettlement::where('payment_id', $payment->id)->count()
        );

        $this->assertEquals(
            100000,
            (float) $first->gross_amount
        );

        $this->assertEquals(
            (float) LedgerEntry::sum('debit'),
            (float) LedgerEntry::sum('credit')
        );

        $this->assertGreaterThan(
            0,
            $finance->availableBalance($business->id)
        );
    }

    /**
     * Withdrawal tidak boleh melebihi saldo tersedia.
     */
    public function test_withdrawal_tidak_boleh_melebihi_saldo_dan_payout_mencatat_ledger(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000);

        $booking = $this->makeBooking(
            $customer,
            $business,
            $pet,
            [$service->id => 1]
        );

        $payment = $booking->payment;

        $payment->update([
            'status' => Payment::PAID,
            'paid_at' => now(),
        ]);

        $finance = app(PaymentFinancialService::class);

        $finance->settle($payment->fresh());

        $this->expectException(ValidationException::class);

        $finance->requestWithdrawal(
            $business->id,
            $owner->id,
            [
                'amount' => 999999,
                'bank_name' => 'BCA',
                'bank_account_number' => '123456',
                'bank_account_name' => 'Owner',
                'idempotency_key' => (string) Str::uuid(),
            ]
        );
    }

    /**
     * Webhook Midtrans memiliki HMAC valid dan idempotent.
     */
    public function test_webhook_midtrans_valid_hmac_idempotent_dan_membuat_settlement(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000);

        $booking = $this->makeBooking(
            $customer,
            $business,
            $pet,
            [$service->id => 1]
        );

        $payment = $booking->payment;

        $payment->update([
            'gateway_provider' => 'midtrans',
            'gateway_reference' => 'ORDER-TEST-1',
            'gateway_status' => 'pending',
        ]);

        config([
            'services.midtrans.server_key' => 'sandbox-test-secret',
        ]);

        $gateway = app(PaymentGatewayService::class);

        $payload = [
            'order_id' => 'ORDER-TEST-1',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'MID-TEST-1',
            'payment_type' => 'qris',
        ];

        $payload['signature_key'] = $gateway->generateSignature(
            $payload['order_id'],
            $payload['status_code'],
            $payload['gross_amount']
        );

        $first = $gateway->processWebhook($payload);
        $second = $gateway->processWebhook($payload);

        $this->assertFalse($first['idempotent']);
        $this->assertTrue($second['idempotent']);

        $this->assertSame(
            Payment::PAID,
            $payment->fresh()->status
        );

        $this->assertSame(
            1,
            PaymentSettlement::where('payment_id', $payment->id)->count()
        );
    }

    /**
     * Midtrans QRIS menyimpan payment attempt dan QR URL.
     */
    public function test_midtrans_qris_menyimpan_attempt_dan_qr_url(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000);

        $booking = $this->makeBooking(
            $customer,
            $business,
            $pet,
            [$service->id => 1]
        );

        config([
            'services.midtrans.server_key' => 'sandbox-test-secret',
        ]);

        Http::fake([
            '*' => Http::response([
                'transaction_id' => 'MID-QR-1',
                'actions' => [
                    [
                        'name' => 'generate-qr-code',
                        'url' => 'https://sandbox.example.test/qr/1',
                    ],
                ],
            ], 200),
        ]);

        $payment = app(PaymentGatewayService::class)
            ->createTransaction($booking, $customer, 'qris');

        $this->assertSame(
            'https://sandbox.example.test/qr/1',
            $payment->gateway_payment_url
        );

        $this->assertSame(
            1,
            PaymentAttempt::where('payment_id', $payment->id)->count()
        );

        Http::assertSent(
            fn ($request) =>
                str_ends_with($request->url(), '/v2/charge')
                && $request['payment_type'] === 'qris'
        );
    }

    /**
     * Withdrawal disetujui, dibayar, dan dicatat dalam ledger.
     */
    public function test_withdrawal_approved_paid_dan_deducted_from_ledger(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000);

        $booking = $this->makeBooking(
            $customer,
            $business,
            $pet,
            [$service->id => 1]
        );

        $payment = $booking->payment;

        $payment->update([
            'status' => Payment::PAID,
            'paid_at' => now(),
        ]);

        $finance = app(PaymentFinancialService::class);

        $finance->settle($payment->fresh());

        $available = $finance->availableBalance($business->id);

        $withdrawal = $finance->requestWithdrawal(
            $business->id,
            $owner->id,
            [
                'amount' => $available,
                'bank_name' => 'BCA',
                'bank_account_number' => '123456',
                'bank_account_name' => 'Owner',
                'idempotency_key' => (string) Str::uuid(),
            ]
        );

        $this->assertEquals(
            0,
            $finance->availableBalance($business->id)
        );

        $admin = $this->makeUser(User::ROLE_ADMIN);

        $finance->approveWithdrawal(
            $withdrawal,
            $admin->id
        );

        $finance->markWithdrawalPaid(
            $withdrawal,
            $admin->id,
            'TRF-123'
        );

        $this->assertSame(
            Withdrawal::PAID,
            $withdrawal->fresh()->status
        );

        $this->assertEquals(
            0,
            $finance->availableBalance($business->id)
        );

        $this->assertSame(
            1,
            LedgerEntry::where(
                'idempotency_key',
                'withdrawal:'.$withdrawal->id.':business'
            )->count()
        );
    }

    /**
     * Refund membalik ledger hanya satu kali.
     */
    public function test_refund_membalik_ledger_satu_kali(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000);

        $booking = $this->makeBooking(
            $customer,
            $business,
            $pet,
            [$service->id => 1]
        );

        $payment = $booking->payment;

        $payment->update([
            'status' => Payment::PAID,
            'paid_at' => now(),
        ]);

        $finance = app(PaymentFinancialService::class);

        $finance->settle($payment->fresh());

        $before = $finance->availableBalance($business->id);

        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'requested_by' => $owner->id,
            'amount' => 10000,
            'reason' => 'Pengembalian sebagian',
            'status' => 'requested',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $finance->completeRefund($refund);

        $finance->completeRefund($refund);

        $this->assertEquals(
            $before - 9000,
            $finance->availableBalance($business->id)
        );

        $this->assertSame(
            3,
            LedgerEntry::where('source_type', 'refund')->count()
        );
    }
}