<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    private $owner; private $biz; private $cust; private $booking;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        [$this->owner, $this->biz] = $this->makeOwnerWithBusiness('A');
        $this->cust = $this->makeUser();
        $pet = $this->makePet($this->cust);
        $svc = $this->makeService($this->biz, 100000);
        $this->booking = $this->makeBooking($this->cust, $this->biz, $pet, [$svc->id => 1]);
    }

    public function test_customer_mengunggah_bukti_pembayaran(): void
    {
        $this->actingAs($this->cust)->post(route('customer.bookings.pay.store', $this->booking->id), [
            'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $pay = $this->booking->payment->fresh();
        $this->assertSame(Payment::PENDING, $pay->status);
        Storage::disk('local')->assertExists($pay->proof_path);
    }

    public function test_file_bukti_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->cust)->post(route('customer.bookings.pay.store', $this->booking->id), [
            'proof' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('proof');
        $this->assertSame(Payment::UNPAID, $this->booking->payment->fresh()->status);
    }

    public function test_owner_memverifikasi_pembayaran_dan_mencatat_verifikator(): void
    {
        $this->booking->payment->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/a.png']);

        $this->actingAs($this->owner)->patch(route('owner.payments.verify', $this->booking->payment->id))->assertSessionHasNoErrors();

        $pay = $this->booking->payment->fresh();
        $this->assertSame(Payment::PAID, $pay->status);
        $this->assertSame($this->owner->id, $pay->verified_by);
        $this->assertNotNull($pay->verified_at);
        $this->assertNotNull($pay->paid_at);
    }

    public function test_penolakan_wajib_disertai_alasan(): void
    {
        $this->booking->payment->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/a.png']);
        $this->actingAs($this->owner);

        $this->patch(route('owner.payments.reject', $this->booking->payment->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(Payment::PENDING, $this->booking->payment->fresh()->status);

        $this->patch(route('owner.payments.reject', $this->booking->payment->id), ['reason' => 'Nominal tidak sesuai'])->assertSessionHasNoErrors();
        $pay = $this->booking->payment->fresh();
        $this->assertSame(Payment::REJECTED, $pay->status);
        $this->assertSame('Nominal tidak sesuai', $pay->reject_reason);
    }

    public function test_tidak_bisa_verifikasi_tanpa_bukti(): void
    {
        $this->actingAs($this->owner)->patch(route('owner.payments.verify', $this->booking->payment->id))->assertSessionHas('error');
        $this->assertSame(Payment::UNPAID, $this->booking->payment->fresh()->status);
    }

    public function test_bukti_pembayaran_mengembalikan_byte_identik_untuk_jpeg_png_webp_dan_pdf(): void
    {
        $samples = [
            'jpg' => [
                'mime' => 'image/jpeg',
                'bytes' => hex2bin('ffd8ffe000104a46494600010101004800480000ffdb004300080606070605080707070909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c1c2837292c30313434341f27393d38323c2e333432ffc0000b080001000101011100ffc40014000100000000000000000000000000000008ffc40014100100000000000000000000000000000000ffda0008010100003f007f00ffd9'),
            ],
            'png' => [
                'mime' => 'image/png',
                'bytes' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            ],
            'webp' => [
                'mime' => 'image/webp',
                'bytes' => base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA='),
            ],
            'pdf' => [
                'mime' => 'application/pdf',
                'bytes' => "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n",
            ],
        ];

        $pay = $this->booking->payment;

        foreach ($samples as $ext => $sample) {
            $path = "payment-proofs/sample.{$ext}";
            Storage::disk('local')->put($path, $sample['bytes']);
            $pay->update(['status' => Payment::PENDING, 'proof_path' => $path]);

            $response = $this->actingAs($this->owner)->get(route('payments.proof', $pay->id));

            $response->assertOk();
            $response->assertHeader('Content-Type', $sample['mime']);
            $response->assertHeader('Content-Length', (string) strlen($sample['bytes']));
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($sample['bytes'], $response->getContent(), "Byte response {$ext} berubah atau tercemar whitespace");
        }
    }

    public function test_bukti_pembayaran_menolak_file_hilang_path_traversal_dan_format_tidak_didukung(): void
    {
        $pay = $this->booking->payment;
        $this->actingAs($this->owner);

        // 1. File tidak ditemukan di disk
        $pay->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/tidak-ada.jpg']);
        $this->get(route('payments.proof', $pay->id))->assertNotFound();

        // 2. Directory traversal / di luar direktori payment-proofs
        Storage::disk('local')->put('rahasia.jpg', hex2bin('ffd8ffe000104a46494600010101004800480000ffd9'));
        $pay->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/../rahasia.jpg']);
        $this->get(route('payments.proof', $pay->id))->assertNotFound();

        $pay->update(['status' => Payment::PENDING, 'proof_path' => 'rahasia.jpg']);
        $this->get(route('payments.proof', $pay->id))->assertNotFound();

        // 3. Ekstensi atau isi file tidak didukung (mis. HTML/teks menyamar sebagai .jpg)
        Storage::disk('local')->put('payment-proofs/palsu.jpg', '<!doctype html><html><body>Bukan gambar</body></html>');
        $pay->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/palsu.jpg']);
        $this->get(route('payments.proof', $pay->id))->assertNotFound();

        Storage::disk('local')->put('payment-proofs/script.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $pay->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/script.svg']);
        $this->get(route('payments.proof', $pay->id))->assertNotFound();
    }

    public function test_halaman_verifikasi_owner_merender_tag_img_bukti_pembayaran(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('local')->put('payment-proofs/valid.png', $png);
        $this->booking->payment->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/valid.png']);

        $this->actingAs($this->owner)
            ->get(route('owner.payments.show', $this->booking->payment->id))
            ->assertOk()
            ->assertSee(route('payments.proof', $this->booking->payment->id), false)
            ->assertSee('class="proof"', false);
    }
}