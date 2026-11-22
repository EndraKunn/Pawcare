<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\PaymentFinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class TitahFinanceRoutingAndPollingTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    /**
     * Owner dapat melihat saldo bisnis dan mengajukan withdrawal.
     */
    public function test_owner_can_access_withdrawal_page_and_submit_withdrawal_request(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness('Salon Titah');

        // Akun Owner dianggap sudah melakukan verifikasi email.
        $owner->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000);

        $booking = $this->makeBooking(
            $customer,
            $business,
            $pet,
            [$service->id => 1]
        );

        $booking->payment->update([
            'status' => Payment::PAID,
            'paid_at' => now(),
        ]);

        // Membuat settlement dan saldo bisnis.
        app(PaymentFinancialService::class)
            ->settle($booking->payment->fresh());

        // Owner membuka halaman withdrawal.
        $this->actingAs($owner)
            ->get(route('owner.withdrawals.index'))
            ->assertOk()
            ->assertSee('Penarikan saldo bisnis')
            ->assertSee('Rp 90.000');

        // Owner mengajukan penarikan saldo.
        $this->actingAs($owner)
            ->post(route('owner.withdrawals.store'), [
                'amount' => 50000,
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
                'bank_account_name' => 'Pemilik Salon',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('withdrawals', [
            'business_id' => $business->id,
            'amount' => 50000,
            'status' => Withdrawal::REQUESTED,
        ]);
    }

    /**
     * Admin dapat mengelola keuangan, withdrawal, dan refund manual.
     */
    public function test_admin_can_open_finance_page_and_process_withdrawal_and_manual_refund(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness('Klinik Keuangan');

        $admin = $this->makeUser(User::ROLE_ADMIN);
        $customer = $this->makeUser();

        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 200000);

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

        // Membuat settlement pembayaran.
        $finance->settle($payment->fresh());

        // Membuat permintaan withdrawal.
        $withdrawal = $finance->requestWithdrawal(
            $business->id,
            $owner->id,
            [
                'amount' => 100000,
                'bank_name' => 'Mandiri',
                'bank_account_number' => '9876543210',
                'bank_account_name' => 'Klinik Keuangan',
                'idempotency_key' => (string) Str::uuid(),
            ]
        );

        // Owner dan Customer tidak boleh mengakses keuangan admin.
        $this->actingAs($owner)
            ->get(route('admin.finance.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.finance.index'))
            ->assertForbidden();

        // Admin dapat membuka halaman keuangan.
        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertSee('Settlement, ledger &amp; withdrawal', false);

        // Admin menyetujui withdrawal.
        $this->actingAs($admin)
            ->patch(route(
                'admin.finance.withdrawals.approve',
                $withdrawal->id
            ))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            Withdrawal::APPROVED,
            $withdrawal->fresh()->status
        );

        // Admin menandai withdrawal telah dibayar.
        $this->actingAs($admin)
            ->patch(
                route(
                    'admin.finance.withdrawals.paid',
                    $withdrawal->id
                ),
                [
                    'reference' => 'TRF-BCA-20261122',
                ]
            )
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            Withdrawal::PAID,
            $withdrawal->fresh()->status
        );

        // Admin mengajukan refund manual.
        $this->actingAs($admin)
            ->post(
                route(
                    'admin.finance.refunds.store',
                    $payment->id
                ),
                [
                    'amount' => 20000,
                    'reason' => 'Pembatalan sebagian layanan',
                    'idempotency_key' => (string) Str::uuid(),
                ]
            )
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = PaymentRefund::where(
            'payment_id',
            $payment->id
        )->firstOrFail();

        // Admin menyelesaikan refund manual.
        $this->actingAs($admin)
            ->patch(
                route(
                    'admin.finance.refunds.complete',
                    $refund->id
                ),
                [
                    'reference' => 'REF-MANUAL-001',
                ]
            )
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            'succeeded',
            $refund->fresh()->status
        );
    }

    /**
     * Route webhook Midtrans memiliki throttle
     * dan konfigurasi platform fee tersedia.
     */
    public function test_midtrans_webhook_route_has_rate_limiter_and_platform_fee_config_is_defined(): void
    {
        $route = Route::getRoutes()
            ->getByName('webhooks.midtrans');

        $this->assertNotNull($route);

        $this->assertTrue(
            collect($route->gatherMiddleware())
                ->contains(
                    fn (string $middleware) =>
                        str_starts_with($middleware, 'throttle:')
                )
        );

        $this->assertSame(
            10.0,
            (float) config('services.pawcare.platform_fee_percent')
        );
    }
}
