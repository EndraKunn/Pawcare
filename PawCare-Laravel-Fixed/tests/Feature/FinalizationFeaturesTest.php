<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\PawCareStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class FinalizationFeaturesTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    /* =========================================================================
     * FITUR 1 — NOTIFIKASI BOOKING DAN PEMBAYARAN
     * ========================================================================= */

    public function test_notifikasi_booking_dikonfirmasi_selesai_dan_dibatalkan(): void
    {
        [$owner, $biz] = $this->makeOwnerWithBusiness('Happy Paws');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer, 'Milo');
        $svc = $this->makeService($biz, 100000);

        $booking = $this->makeBooking($customer, $biz, $pet, [$svc->id => 1]);
        $this->assertSame(0, $customer->notifications()->count());

        // 1. Transisi tidak valid (Menunggu -> Selesai) ditolak dan TIDAK mengirim notifikasi palsu.
        $this->actingAs($owner)
            ->patch(route('owner.bookings.status', $booking->id), ['status' => Booking::STATUS_DONE])
            ->assertSessionHasErrors('status');
        $this->assertSame(0, $customer->notifications()->count());

        // 2. Owner mengonfirmasi booking -> notifikasi terkirim.
        $this->actingAs($owner)
            ->patch(route('owner.bookings.status', $booking->id), [
                'status' => Booking::STATUS_CONFIRMED,
                'note' => 'Kandang VIP siap',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $customer->unreadNotifications()->count());
        $firstNotif = $customer->notifications()->latest()->first();
        $this->assertStringContainsString($booking->invoice_number, data_get($firstNotif->data, 'message'));
        $this->assertStringContainsString(Booking::STATUS_CONFIRMED, data_get($firstNotif->data, 'message'));

        // 3. Owner mengubah ke Dalam Proses -> bukan status notifikasi utama, jumlah tetap 1.
        $this->actingAs($owner)
            ->patch(route('owner.bookings.status', $booking->id), ['status' => Booking::STATUS_PROGRESS])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $customer->notifications()->count());

        // 4. Owner menyelesaikan booking -> notifikasi selesai terkirim.
        $this->actingAs($owner)
            ->patch(route('owner.bookings.status', $booking->id), ['status' => Booking::STATUS_DONE])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $customer->notifications()->count());

        // 5. Booking kedua dibatalkan -> notifikasi pembatalan terkirim.
        $booking2 = $this->makeBooking($customer, $biz, $pet, [$svc->id => 1]);
        $this->actingAs($owner)
            ->patch(route('owner.bookings.status', $booking2->id), [
                'status' => Booking::STATUS_CANCELLED,
                'note' => 'Jadwal penuh',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $customer->notifications()->count());
    }

    public function test_notifikasi_pembayaran_ditolak_menyertakan_alasan_dan_tautan_unggah_ulang(): void
    {
        Storage::fake('local');
        [$owner, $biz] = $this->makeOwnerWithBusiness('Mitra A');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $svc = $this->makeService($biz, 120000);
        $booking = $this->makeBooking($customer, $biz, $pet, [$svc->id => 1]);

        $booking->payment->update([
            'status' => Payment::PENDING,
            'proof_path' => 'payment-proofs/bukti.png',
        ]);

        $this->actingAs($owner)
            ->patch(route('owner.payments.reject', $booking->payment->id), [
                'reason' => 'Bukti transfer buram dan nominal tidak terbaca',
            ])
            ->assertRedirect(route('owner.payments.index'));

        $this->assertSame(1, $customer->unreadNotifications()->count());
        $notif = $customer->notifications()->first();
        $this->assertSame('Pembayaran ditolak', data_get($notif->data, 'title'));
        $this->assertStringContainsString('Bukti transfer buram dan nominal tidak terbaca', data_get($notif->data, 'message'));
        $this->assertSame(route('customer.bookings.pay', $booking->id), data_get($notif->data, 'url'));
    }

    public function test_notifikasi_pembayaran_diverifikasi(): void
    {
        Storage::fake('local');
        [$owner, $biz] = $this->makeOwnerWithBusiness('Mitra A');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $svc = $this->makeService($biz, 120000);
        $booking = $this->makeBooking($customer, $biz, $pet, [$svc->id => 1]);

        $booking->payment->update([
            'status' => Payment::PENDING,
            'proof_path' => 'payment-proofs/bukti.png',
        ]);

        $this->actingAs($owner)
            ->patch(route('owner.payments.verify', $booking->payment->id))
            ->assertRedirect(route('owner.payments.index'));

        $this->assertSame(1, $customer->unreadNotifications()->count());
        $notif = $customer->notifications()->first();
        $this->assertSame('Pembayaran diverifikasi', data_get($notif->data, 'title'));
        $this->assertStringContainsString($booking->invoice_number, data_get($notif->data, 'message'));
    }

    public function test_isolasi_notifikasi_antar_customer_dan_perlindungan_open_redirect(): void
    {
        $custA = $this->makeUser();
        $custB = $this->makeUser();

        $custA->notify(new PawCareStatusNotification(
            title: 'Notifikasi Aman',
            message: 'Pesan untuk Customer A',
            url: route('customer.bookings.index'),
        ));

        $custA->notify(new PawCareStatusNotification(
            title: 'Notifikasi Jahat Eksternal',
            message: 'Percobaan open redirect',
            url: 'https://evil-phishing.example.com/login',
        ));

        $custA->notify(new PawCareStatusNotification(
            title: 'Notifikasi Protocol Relative',
            message: 'Percobaan protocol relative',
            url: '//evil-phishing.example.com/attack',
        ));

        $notifSafe = $custA->notifications()->where('data', 'like', '%Notifikasi Aman%')->firstOrFail();
        $notifEvil = $custA->notifications()->where('data', 'like', '%Notifikasi Jahat Eksternal%')->firstOrFail();
        $notifProto = $custA->notifications()->where('data', 'like', '%Notifikasi Protocol Relative%')->firstOrFail();

        // Customer B tidak dapat membuka atau melihat notifikasi Customer A.
        $this->actingAs($custB)
            ->get(route('customer.notifications.open', $notifSafe->id))
            ->assertNotFound();

        $this->actingAs($custB)
            ->get(route('customer.notifications.index'))
            ->assertOk()
            ->assertDontSee('Pesan untuk Customer A');

        // Customer A membuka notifikasi internal -> ditandai dibaca & redirect ke halaman internal.
        $this->actingAs($custA)
            ->get(route('customer.notifications.open', $notifSafe->id))
            ->assertRedirect(route('customer.bookings.index'));
        $this->assertNotNull($notifSafe->fresh()->read_at);

        // Customer A membuka notifikasi dengan URL eksternal -> diarahkan aman ke index notifikasi.
        $this->actingAs($custA)
            ->get(route('customer.notifications.open', $notifEvil->id))
            ->assertRedirect(route('customer.notifications.index'));

        $this->actingAs($custA)
            ->get(route('customer.notifications.open', $notifProto->id))
            ->assertRedirect(route('customer.notifications.index'));

        // Tandai semua dibaca.
        $custA->notify(new PawCareStatusNotification('Baru', 'Belum dibaca', route('customer.dashboard')));
        $this->assertGreaterThan(0, $custA->unreadNotifications()->count());

        $this->actingAs($custA)
            ->post(route('customer.notifications.read-all'))
            ->assertRedirect();
        $this->assertSame(0, $custA->unreadNotifications()->count());
    }

    /* =========================================================================
     * FITUR 2 — FILTER PENCARIAN BISNIS (LOKASI, LAYANAN, JENIS HEWAN)
     * ========================================================================= */

    public function test_filter_katalog_lokasi_jenis_layanan_dan_jenis_hewan_terpisah_maupun_bersamaan(): void
    {
        [, $bizMadiun] = $this->makeOwnerWithBusiness('Hotel Hewan Madiun');
        $bizMadiun->update([
            'address' => 'Jl. Pahlawan No. 10, Kota Madiun',
            'accepted_species' => ['Anjing', 'Kucing', 'Kelinci'],
        ]);
        $this->makeService($bizMadiun, 85000, ['category' => 'Penitipan', 'status' => true]);
        $this->makeService($bizMadiun, 150000, ['category' => 'Perawatan', 'status' => false]); // nonaktif

        [, $bizNgawi] = $this->makeOwnerWithBusiness('Salon Anjing Ngawi');
        $bizNgawi->update([
            'address' => 'Jl. Diponegoro No. 5, Ngawi',
            'accepted_species' => ['Anjing'],
        ]);
        $this->makeService($bizNgawi, 100000, ['category' => 'Grooming', 'status' => true]);

        [, $bizMagetan] = $this->makeOwnerWithBusiness('Griya Satwa Magetan');
        $bizMagetan->update([
            'address' => 'Jl. Raya Magetan No. 45, Magetan',
            'accepted_species' => ['Kucing', 'Hamster', 'Reptil'],
        ]);
        $this->makeService($bizMagetan, 120000, ['category' => 'Perawatan', 'status' => true]);

        // 1. Filter lokasi case-insensitive ("mAdIuN")
        $this->get(route('catalog', ['q' => 'mAdIuN']))
            ->assertOk()
            ->assertSee($bizMadiun->business_name)
            ->assertDontSee($bizNgawi->business_name)
            ->assertDontSee($bizMagetan->business_name);

        // 2. Filter jenis layanan aktif ("Perawatan" hanya menampilkan Magetan karena di Madiun nonaktif)
        $this->get(route('catalog', ['category' => 'Perawatan']))
            ->assertOk()
            ->assertSee($bizMagetan->business_name)
            ->assertDontSee($bizMadiun->business_name)
            ->assertDontSee($bizNgawi->business_name);

        // 3. Filter jenis hewan ("Reptil" hanya diterima oleh Magetan)
        $this->get(route('catalog', ['species' => 'Reptil']))
            ->assertOk()
            ->assertSee($bizMagetan->business_name)
            ->assertDontSee($bizMadiun->business_name)
            ->assertDontSee($bizNgawi->business_name);

        // 4. Filter kombinasi (species=Anjing & category=Penitipan & q=madiun)
        $this->get(route('catalog', [
            'q' => 'madiun',
            'category' => 'Penitipan',
            'species' => 'Anjing',
        ]))
            ->assertOk()
            ->assertSee($bizMadiun->business_name)
            ->assertDontSee($bizNgawi->business_name)
            ->assertDontSee($bizMagetan->business_name);

        // 5. Empty state ketika tidak ada yang cocok
        $this->get(route('catalog', [
            'q' => 'Ngawi',
            'species' => 'Reptil',
        ]))
            ->assertOk()
            ->assertSee('Tidak ada bisnis yang cocok');
    }

    /* =========================================================================
     * FITUR 3 — EKSPOR LAPORAN CSV OWNER DAN ADMIN
     * ========================================================================= */

    public function test_ekspor_csv_owner_hanya_berisi_data_bisnis_sendiri_dan_aman_dari_csv_injection(): void
    {
        [$ownerA, $bizA] = $this->makeOwnerWithBusiness('Bisnis Alpha');
        [$ownerB, $bizB] = $this->makeOwnerWithBusiness('Bisnis Beta Rahasia');

        $custA = $this->makeUser('customer', ['name' => '=SUM(1,2) Pelanggan A']);
        $custB = $this->makeUser('customer', ['name' => 'Pelanggan Beta']);

        $petA = $this->makePet($custA, 'Milo');
        $petB = $this->makePet($custB, 'Rocky');

        $svcA = $this->makeService($bizA, 150000, ['name' => 'Penitipan Eksklusif']);
        $svcB = $this->makeService($bizB, 275000, ['name' => 'Grooming Beta']);

        $bookingPaidA = $this->makeBooking($custA, $bizA, $petA, [$svcA->id => 1]);
        $bookingPaidA->update(['notes' => '+cmd|\'/C calc\'!A0']);
        $bookingPaidA->payment->update([
            'status' => Payment::PAID,
            'paid_at' => now(),
            'verified_by' => $ownerA->id,
            'verified_at' => now(),
            'proof_path' => 'payment-proofs/a.png',
        ]);

        // Booking A kedua yang belum dibayar (tidak boleh masuk pendapatan terverifikasi)
        $bookingUnpaidA = $this->makeBooking($custA, $bizA, $petA, [$svcA->id => 2]);

        // Booking bisnis B yang sudah dibayar (tidak boleh bocor ke CSV Owner A)
        $bookingPaidB = $this->makeBooking($custB, $bizB, $petB, [$svcB->id => 1]);
        $bookingPaidB->payment->update([
            'status' => Payment::PAID,
            'paid_at' => now(),
            'verified_by' => $ownerB->id,
            'verified_at' => now(),
            'proof_path' => 'payment-proofs/b.png',
        ]);

        $response = $this->actingAs($ownerA)->get(route('owner.reports.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('laporan-bisnis-', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();

        // Data bisnis A ada, data bisnis B tidak ada
        $this->assertStringContainsString('Bisnis Alpha', $csv);
        $this->assertStringContainsString($bookingPaidA->invoice_number, $csv);
        $this->assertStringContainsString($bookingUnpaidA->invoice_number, $csv);
        $this->assertStringNotContainsString('Bisnis Beta Rahasia', $csv);
        $this->assertStringNotContainsString($bookingPaidB->invoice_number, $csv);
        $this->assertStringNotContainsString('Pelanggan Beta', $csv);

        // Pendapatan terverifikasi hanya Rp 150.000 (bukan Rp 450.000)
        $this->assertStringContainsString('"Total Pendapatan Terverifikasi (Rp)",150000', $csv);

        // Formula injection disanitasi dengan prefix kutip tunggal (')
        $this->assertStringContainsString("'=SUM(1,2) Pelanggan A", $csv);
        $this->assertStringContainsString("'+cmd|", $csv);

        // Tidak ada password hash
        $this->assertStringNotContainsString($ownerA->password, $csv);
    }

    public function test_ekspor_csv_admin_mencakup_seluruh_platform_dan_validasi_tanggal(): void
    {
        [$ownerA, $bizA] = $this->makeOwnerWithBusiness('Alpha Hotel');
        [$ownerB, $bizB] = $this->makeOwnerWithBusiness('Beta Grooming');
        $admin = $this->makeUser('admin');
        $cust = $this->makeUser();
        $pet = $this->makePet($cust);

        $svcA = $this->makeService($bizA, 100000);
        $svcB = $this->makeService($bizB, 200000);

        $bkA = $this->makeBooking($cust, $bizA, $pet, [$svcA->id => 1]);
        $bkA->payment->update(['status' => Payment::PAID, 'paid_at' => now(), 'proof_path' => 'payment-proofs/a.png']);

        $bkB = $this->makeBooking($cust, $bizB, $pet, [$svcB->id => 1]);
        $bkB->payment->update(['status' => Payment::PAID, 'paid_at' => now(), 'proof_path' => 'payment-proofs/b.png']);

        // Owner tidak boleh mengakses ekspor Admin
        $this->actingAs($ownerA)->get(route('admin.reports.export'))->assertForbidden();

        // Validasi rentang tanggal (to < from ditolak)
        $this->actingAs($admin)->get(route('admin.reports.export', [
            'from' => '2026-10-20',
            'to' => '2026-10-01',
        ]))->assertSessionHasErrors('to');

        // Ekspor CSV Admin berhasil dan mencakup kedua bisnis
        $response = $this->actingAs($admin)->get(route('admin.reports.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('laporan-platform-pawcare-', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Alpha Hotel', $csv);
        $this->assertStringContainsString('Beta Grooming', $csv);
        $this->assertStringContainsString('"Total Pendapatan Platform Terverifikasi (Rp)",300000', $csv);
        $this->assertStringNotContainsString($admin->password, $csv);
    }
}
