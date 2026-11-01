<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\PaymentGatewayLog;
use App\Models\PetCareNote;
use App\Models\PetHealthRecord;
use App\Models\Review;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class SixMajorFeaturesTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    private function validPng(string $name = 'dokumen.png'): UploadedFile
    {
        $bin = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $bin);
    }

    /* =========================================================================
     * FITUR 1 — RATING DAN ULASAN PELANGGAN
     * ========================================================================= */

    public function test_fitur_1_rating_dan_ulasan_pelanggan_serta_balasan_owner(): void
    {
        [$ownerA, $bizA] = $this->makeOwnerWithBusiness('Happy Paws');
        [$ownerB, $bizB] = $this->makeOwnerWithBusiness('Bisnis Lain');
        $custA = $this->makeUser();
        $custB = $this->makeUser();
        $petA = $this->makePet($custA, 'Milo');
        $svcA = $this->makeService($bizA, 100000);

        $booking = $this->makeBooking($custA, $bizA, $petA, [$svcA->id => 1]);

        // 1. Booking yang belum selesai (Menunggu) tidak dapat diulas.
        $this->actingAs($custA)
            ->post(route('customer.reviews.store', $booking->id), ['rating' => 5, 'comment' => 'Bagus'])
            ->assertSessionHas('error');
        $this->assertSame(0, Review::count());

        // 2. Ubah status booking menjadi Selesai.
        $booking->update(['status' => Booking::STATUS_DONE]);

        // 3. Pelanggan lain tidak dapat mengulas booking milik Customer A.
        $this->actingAs($custB)
            ->post(route('customer.reviews.store', $booking->id), ['rating' => 5, 'comment' => 'Bajak'])
            ->assertNotFound();

        // 4. Rating tidak valid (< 1 atau > 5) ditolak.
        $this->actingAs($custA)
            ->post(route('customer.reviews.store', $booking->id), ['rating' => 6, 'comment' => 'Terlalu tinggi'])
            ->assertSessionHasErrors('rating');

        // 5. Ulasan yang valid berhasil disimpan.
        $this->actingAs($custA)
            ->post(route('customer.reviews.store', $booking->id), [
                'rating' => 5,
                'comment' => 'Pelayanan sangat ramah dan bersih!',
            ])
            ->assertSessionHas('success');

        $review = Review::firstOrFail();
        $this->assertSame(5, $review->rating);
        $this->assertSame($bizA->id, $review->business_id);

        // 6. Ulasan ganda pada booking yang sama ditolak.
        $this->actingAs($custA)
            ->post(route('customer.reviews.store', $booking->id), ['rating' => 4, 'comment' => 'Ulasan kedua'])
            ->assertSessionHas('error');
        $this->assertSame(1, Review::count());

        // 7. Owner bisnis B tidak dapat membalas ulasan bisnis A.
        $this->actingAs($ownerB)
            ->post(route('owner.reviews.reply', $review->id), ['reply' => 'Halo'])
            ->assertNotFound();

        // 8. Owner bisnis A membalas ulasan sebagai entitas terpisah tanpa mengubah isi ulasan pelanggan.
        $this->actingAs($ownerA)
            ->post(route('owner.reviews.reply', $review->id), ['reply' => 'Terima kasih atas kepercayaannya!'])
            ->assertSessionHas('success');

        $this->assertSame('Pelayanan sangat ramah dan bersih!', $review->fresh()->comment);
        $this->assertSame('Terima kasih atas kepercayaannya!', $review->fresh()->reply->reply);

        // 9. Katalog dan halaman bisnis menampilkan rating rata-rata dan ulasan.
        $this->get(route('catalog'))
            ->assertOk()
            ->assertSee('5,0');

        $this->get(route('catalog.show', $bizA->id))
            ->assertOk()
            ->assertSee('Pelayanan sangat ramah dan bersih!')
            ->assertSee('Terima kasih atas kepercayaannya!');
    }

    /* =========================================================================
     * FITUR 2 — KALENDER BOOKING DAN KETERSEDIAAN SLOT
     * ========================================================================= */

    public function test_fitur_2_kalender_booking_kapasitas_slot_dan_waktu_istirahat(): void
    {
        [$owner, $biz] = $this->makeOwnerWithBusiness('Paw Hotel');
        $cust1 = $this->makeUser();
        $cust2 = $this->makeUser();
        $cust3 = $this->makeUser();
        $pet1 = $this->makePet($cust1, 'Anabul 1');
        $pet2 = $this->makePet($cust2, 'Anabul 2');
        $pet3 = $this->makePet($cust3, 'Anabul 3');
        $svc = $this->makeService($biz, 95000, ['duration_minutes' => 60]);

        $targetDate = now()->addDays(3)->toDateString();

        // Setel kapasitas slot = 2 dan waktu istirahat 12:00-13:00 untuk semua hari.
        $hoursPayload = [];
        for ($d = 1; $d <= 7; $d++) {
            $hoursPayload[$d] = [
                'opens_at' => '08:00',
                'closes_at' => '17:00',
                'slot_duration_minutes' => 60,
                'slot_capacity' => 2,
                'break_starts_at' => '12:00',
                'break_ends_at' => '13:00',
            ];
        }

        $this->actingAs($owner)
            ->put(route('owner.business.schedule.update'), ['hours' => $hoursPayload])
            ->assertSessionHasNoErrors();

        // 1. Booking pada jam istirahat (12:00) ditolak.
        $this->actingAs($cust1)
            ->post(route('customer.bookings.store'), $this->bookingPayload($biz, $pet1, [$svc->id], [
                'booking_date' => $targetDate,
                'booking_time' => '12:00',
            ]))
            ->assertSessionHasErrors('booking_time');

        // 2. Dua booking pada jam 10:00 berhasil (sesuai kapasitas = 2).
        $this->actingAs($cust1)
            ->post(route('customer.bookings.store'), $this->bookingPayload($biz, $pet1, [$svc->id], [
                'booking_date' => $targetDate,
                'booking_time' => '10:00',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($cust2)
            ->post(route('customer.bookings.store'), $this->bookingPayload($biz, $pet2, [$svc->id], [
                'booking_date' => $targetDate,
                'booking_time' => '10:00',
            ]))
            ->assertSessionHasNoErrors();

        // 3. Booking ketiga pada jam 10:00 ditolak karena slot sudah penuh.
        $this->actingAs($cust3)
            ->post(route('customer.bookings.store'), $this->bookingPayload($biz, $pet3, [$svc->id], [
                'booking_date' => $targetDate,
                'booking_time' => '10:00',
            ]))
            ->assertSessionHasErrors('booking_time');

        // 4. Ketika salah satu booking dibatalkan, slot kembali tersedia untuk pelanggan ketiga.
        $firstBooking = Booking::where('customer_id', $cust1->id)->firstOrFail();
        $this->actingAs($cust1)
            ->patch(route('customer.bookings.cancel', $firstBooking->id))
            ->assertSessionHas('success');

        $this->actingAs($cust3)
            ->post(route('customer.bookings.store'), $this->bookingPayload($biz, $pet3, [$svc->id], [
                'booking_date' => $targetDate,
                'booking_time' => '10:00',
            ]))
            ->assertSessionHasNoErrors();

        // 5. Kalender Owner menampilkan jadwal pada mode harian, mingguan, dan bulanan.
        foreach (['day', 'week', 'month'] as $mode) {
            $this->actingAs($owner)
                ->get(route('owner.calendar.index', ['mode' => $mode, 'date' => $targetDate]))
                ->assertOk()
                ->assertSee('Anabul 2');
        }
    }

    /* =========================================================================
     * FITUR 3 — CHAT PELANGGAN DAN OWNER
     * ========================================================================= */

    public function test_fitur_3_chat_pelanggan_dan_owner_dengan_konteks_booking_dan_isolasi_tenant(): void
    {
        [$ownerA, $bizA] = $this->makeOwnerWithBusiness('Salon A');
        [$ownerB, $bizB] = $this->makeOwnerWithBusiness('Salon B');
        $custA = $this->makeUser();
        $custB = $this->makeUser();
        $petA = $this->makePet($custA, 'Milo');
        $svcA = $this->makeService($bizA, 80000);
        $bookingA = $this->makeBooking($custA, $bizA, $petA, [$svcA->id => 1]);

        // 1. Customer A memulai percakapan terkait booking A dan mengirim pesan.
        $this->actingAs($custA)
            ->post(route('customer.chat.start'), [
                'business_id' => $bizA->id,
                'booking_id' => $bookingA->id,
                'body' => 'Halo kak, apakah Milo perlu membawa makanan sendiri? <script>alert(1)</script>',
            ])
            ->assertRedirect();

        $conv = Conversation::firstOrFail();
        $this->assertSame($bookingA->id, $conv->booking_id);
        $this->assertSame(1, $conv->messages()->count());

        // 2. Customer B dan Owner B tidak dapat mengakses atau mengirim pesan ke percakapan ini.
        $this->actingAs($custB)->get(route('customer.chat.show', $conv->id))->assertNotFound();
        $this->actingAs($custB)->post(route('customer.chat.send', $conv->id), ['body' => 'Intip'])->assertNotFound();
        $this->actingAs($ownerB)->get(route('owner.chat.show', $conv->id))->assertNotFound();
        $this->actingAs($ownerB)->post(route('owner.chat.send', $conv->id), ['body' => 'Bajak'])->assertNotFound();

        // 3. Owner A membuka percakapan -> pesan ditandai sudah dibaca & XSS di-escape.
        $this->actingAs($ownerA)
            ->get(route('owner.chat.show', $conv->id))
            ->assertOk()
            ->assertSee($bookingA->invoice_number)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $this->assertNotNull($conv->messages()->first()->fresh()->read_at);

        // 4. Owner A membalas pesan.
        $this->actingAs($ownerA)
            ->post(route('owner.chat.send', $conv->id), [
                'body' => 'Boleh kak, makanan rutin sudah termasuk tetapi pakan khusus boleh dibawa.',
            ])
            ->assertRedirect(route('owner.chat.show', $conv->id));

        $this->assertSame(2, $conv->messages()->count());
    }

    /* =========================================================================
     * FITUR 4 — INTEGRASI PAYMENT GATEWAY (SANDBOX, WEBHOOK, IDEMPOTENSI)
     * ========================================================================= */

    public function test_fitur_4_payment_gateway_sandbox_verifikasi_signature_webhook_dan_idempotensi(): void
    {
        [$owner, $biz] = $this->makeOwnerWithBusiness('Klinik Satwa');
        $cust = $this->makeUser();
        $otherCust = $this->makeUser();
        $pet = $this->makePet($cust, 'Milo');
        $svc = $this->makeService($biz, 175000);
        $booking = $this->makeBooking($cust, $biz, $pet, [$svc->id => 1]);

        // 1. Pelanggan lain tidak dapat membuat transaksi gateway untuk booking ini.
        $this->actingAs($otherCust)
            ->post(route('customer.bookings.pay.gateway', $booking->id), ['channel' => 'qris'])
            ->assertNotFound();

        // 2. Customer membuat sesi pembayaran Payment Gateway -> nominal menggunakan database (175000).
        $this->actingAs($cust)
            ->post(route('customer.bookings.pay.gateway', $booking->id), [
                'channel' => 'bca_va',
                'amount' => 1, // dicoba dipalsukan dari browser
            ])
            ->assertRedirect(route('customer.bookings.pay', $booking->id));

        $pay = $booking->payment->fresh();
        $this->assertSame(Payment::METHOD_GATEWAY, $pay->payment_method);
        $this->assertSame(Payment::GATEWAY_PENDING, $pay->gateway_status);
        $this->assertSame(175000.0, $pay->amount);
        $this->assertNotNull($pay->gateway_reference);

        $gateway = app(PaymentGatewayService::class);
        $orderId = $pay->gateway_reference;
        $grossAmount = '175000.00';

        // 3. Webhook dengan signature tidak valid ditolak (403 Forbidden) dan tidak mengubah status.
        $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'signature_key' => 'signature-palsu',
        ])->assertForbidden();

        $this->assertSame(Payment::UNPAID, $pay->fresh()->status);

        // 4. Webhook dengan nominal tidak sesuai tagihan ditolak (422).
        $wrongAmountSig = $gateway->generateSignature($orderId, '200', '10000.00');
        $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => '10000.00',
            'transaction_status' => 'settlement',
            'signature_key' => $wrongAmountSig,
        ])->assertStatus(422);

        $this->assertSame(Payment::UNPAID, $pay->fresh()->status);

        // 5. Webhook valid ('settlement') mengubah status menjadi Dibayar dan mengirim notifikasi.
        $validSig = $gateway->generateSignature($orderId, '200', $grossAmount);
        $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'MID-TRX-999',
            'payment_type' => 'bca_va',
            'signature_key' => $validSig,
        ])->assertOk()->assertJson(['status' => 'ok', 'idempotent' => false]);

        $this->assertSame(Payment::PAID, $pay->fresh()->status);
        $this->assertSame(Payment::GATEWAY_SETTLEMENT, $pay->fresh()->gateway_status);
        $this->assertSame(1, PaymentGatewayLog::where('payment_id', $pay->id)->count());
        $this->assertSame(1, $cust->notifications()->count());

        // 6. Callback webhook berulang (idempotensi) tidak menggandakan log atau notifikasi.
        $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'MID-TRX-999',
            'payment_type' => 'bca_va',
            'signature_key' => $validSig,
        ])->assertOk()->assertJson(['status' => 'ok', 'idempotent' => true]);

        $this->assertSame(1, PaymentGatewayLog::where('payment_id', $pay->id)->count());
        $this->assertSame(1, $cust->notifications()->count());
    }

    /* =========================================================================
     * FITUR 5 — DASHBOARD ANALITIK OWNER DAN ADMIN
     * ========================================================================= */

    public function test_fitur_5_dashboard_analitik_owner_dan_admin_dengan_filter_periode(): void
    {
        [$ownerA, $bizA] = $this->makeOwnerWithBusiness('Analitik Alpha');
        [$ownerB, $bizB] = $this->makeOwnerWithBusiness('Analitik Beta');
        $admin = $this->makeUser('admin');
        $cust = $this->makeUser();
        $pet = $this->makePet($cust);

        $svcA = $this->makeService($bizA, 120000);
        $svcB = $this->makeService($bizB, 250000);

        $bkA = $this->makeBooking($cust, $bizA, $pet, [$svcA->id => 1]);
        $bkA->update(['status' => Booking::STATUS_DONE]);
        $bkA->payment->update(['status' => Payment::PAID, 'paid_at' => now(), 'proof_path' => 'payment-proofs/a.png']);

        Review::create([
            'booking_id' => $bkA->id,
            'customer_id' => $cust->id,
            'business_id' => $bizA->id,
            'rating' => 5,
            'comment' => 'Mantap',
        ]);

        $bkB = $this->makeBooking($cust, $bizB, $pet, [$svcB->id => 1]);
        $bkB->payment->update(['status' => Payment::PAID, 'paid_at' => now(), 'proof_path' => 'payment-proofs/b.png']);

        foreach (['today', '7d', '30d', 'month'] as $p) {
            $this->actingAs($ownerA)
                ->get(route('owner.dashboard', ['period' => $p]))
                ->assertOk()
                ->assertSee('Rp 120.000')
                ->assertDontSee('Rp 250.000')
                ->assertSee('5,0 ★');

            $this->actingAs($admin)
                ->get(route('admin.dashboard', ['period' => $p]))
                ->assertOk()
                ->assertSee('Rp 370.000')
                ->assertSee('Analitik Alpha')
                ->assertSee('Analitik Beta');
        }
    }

    /* =========================================================================
     * FITUR 6 — RIWAYAT KESEHATAN DAN PERAWATAN HEWAN
     * ========================================================================= */

    public function test_fitur_6_riwayat_kesehatan_vaksinasi_lampiran_privat_dan_catatan_perawatan_owner(): void
    {
        Storage::fake('local');
        [$ownerA, $bizA] = $this->makeOwnerWithBusiness('Griya Care A');
        [$ownerB, $bizB] = $this->makeOwnerWithBusiness('Griya Care B');
        $custA = $this->makeUser();
        $custB = $this->makeUser();
        $petA = $this->makePet($custA, 'Luna');
        $svcA = $this->makeService($bizA, 110000);
        $bookingA = $this->makeBooking($custA, $bizA, $petA, [$svcA->id => 1]);

        // 1. Customer A menambahkan catatan vaksinasi beserta lampiran PNG valid.
        $this->actingAs($custA)
            ->post(route('customer.pets.health.store', $petA->id), [
                'record_type' => PetHealthRecord::TYPE_VACCINATION,
                'title' => 'Vaksin Tricat Tahunan',
                'recorded_date' => now()->subMonths(6)->toDateString(),
                'next_due_date' => now()->addMonths(6)->toDateString(),
                'notes' => 'Tidak ada efek samping',
                'attachment' => $this->validPng('sertifikat-vaksin.png'),
            ])
            ->assertSessionHas('success');

        $record = PetHealthRecord::firstOrFail();
        $this->assertNotNull($record->attachment_path);
        Storage::disk('local')->assertExists($record->attachment_path);

        // 2. Lampiran tidak valid (teks menyamar sebagai .jpg) ditolak.
        $fakeJpg = UploadedFile::fake()->createWithContent('palsu.jpg', 'bukan file gambar');
        $this->actingAs($custA)
            ->post(route('customer.pets.health.store', $petA->id), [
                'record_type' => PetHealthRecord::TYPE_ALLERGY,
                'title' => 'Alergi Ikan Laut',
                'attachment' => $fakeJpg,
            ])
            ->assertSessionHasErrors('attachment');

        // 3. Customer B dan Owner B (yang tidak menangani booking Luna) ditolak mengakses kesehatan & lampiran.
        $this->actingAs($custB)->get(route('customer.pets.health.show', $petA->id))->assertNotFound();
        $this->actingAs($custB)->get(route('pets.health.attachment', $record->id))->assertNotFound();
        $this->actingAs($ownerB)->get(route('pets.health.attachment', $record->id))->assertNotFound();

        // 4. Owner A (yang menangani booking Luna) dapat melihat lampiran selama izin berbagi aktif.
        $this->actingAs($ownerA)->get(route('pets.health.attachment', $record->id))->assertOk();

        // 5. Jika Customer A menonaktifkan izin berbagi kesehatan ke bisnis, Owner A tidak lagi dapat membuka lampiran.
        $this->actingAs($custA)->patch(route('customer.pets.health.share', $petA->id))->assertSessionHas('success');
        $this->assertFalse($petA->fresh()->share_health_with_business);
        $this->actingAs($ownerA)->get(route('pets.health.attachment', $record->id))->assertNotFound();

        // 6. Owner A mencatat hasil perawatan bisnis pasca-layanan pada booking A.
        $this->actingAs($ownerA)
            ->post(route('owner.care-notes.store', $bookingA->id), [
                'care_summary' => 'Grooming dan pemeriksaan kuku selesai dengan baik.',
                'recommendations' => 'Jaga kelembapan telinga setiap minggu.',
                'visible_to_customer' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, PetCareNote::count());

        // 7. Customer A dapat melihat catatan hasil perawatan bisnis di halaman kesehatan hewan & detail booking.
        $this->actingAs($custA)
            ->get(route('customer.pets.health.show', $petA->id))
            ->assertOk()
            ->assertSee('Vaksin Tricat Tahunan')
            ->assertSee('Grooming dan pemeriksaan kuku selesai dengan baik.');
    }
}
