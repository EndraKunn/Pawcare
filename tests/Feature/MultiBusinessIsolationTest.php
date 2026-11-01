<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class MultiBusinessIsolationTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    private $ownerA; private $bizA; private $ownerB; private $bizB;
    private $custA; private $custB; private $petA; private $petB;
    private $svcA; private $svcB; private $bookingA; private $bookingB;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->ownerA, $this->bizA] = $this->makeOwnerWithBusiness('A');
        [$this->ownerB, $this->bizB] = $this->makeOwnerWithBusiness('B');
        $this->custA = $this->makeUser();
        $this->custB = $this->makeUser();
        $this->petA = $this->makePet($this->custA, 'PetA');
        $this->petB = $this->makePet($this->custB, 'PetB');
        $this->svcA = $this->makeService($this->bizA, 100000);
        $this->svcB = $this->makeService($this->bizB, 250000);
        $this->bookingA = $this->makeBooking($this->custA, $this->bizA, $this->petA, [$this->svcA->id => 1]);
        $this->bookingB = $this->makeBooking($this->custB, $this->bizB, $this->petB, [$this->svcB->id => 1]);
    }

    public function test_owner_a_tidak_bisa_membuka_booking_bisnis_b(): void
    {
        $this->actingAs($this->ownerA);
        $this->get(route('owner.bookings.show', $this->bookingA->id))->assertOk();
        $this->get(route('owner.bookings.show', $this->bookingB->id))->assertNotFound();
    }

    public function test_owner_a_tidak_bisa_mengubah_status_booking_bisnis_b(): void
    {
        $this->actingAs($this->ownerA)
            ->patch(route('owner.bookings.status', $this->bookingB->id), ['status' => 'Dikonfirmasi'])
            ->assertNotFound();
        $this->assertSame('Menunggu', $this->bookingB->fresh()->status);
    }

    public function test_owner_a_tidak_bisa_mengubah_atau_menghapus_layanan_bisnis_b(): void
    {
        $this->actingAs($this->ownerA);
        $this->get(route('owner.services.edit', $this->svcB->id))->assertNotFound();
        $this->put(route('owner.services.update', $this->svcB->id), ['name' => 'Hack', 'category' => 'Grooming', 'unit' => 'sesi', 'price' => 1])->assertNotFound();
        $this->delete(route('owner.services.destroy', $this->svcB->id))->assertNotFound();
        $this->patch(route('owner.services.toggle', $this->svcB->id))->assertNotFound();
        $this->assertSame(250000.0, $this->svcB->fresh()->price);
    }

    public function test_owner_a_tidak_bisa_memverifikasi_atau_menolak_pembayaran_bisnis_b(): void
    {
        $paymentB = $this->bookingB->payment;
        $paymentB->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/x.png']);

        $this->actingAs($this->ownerA);
        $this->patch(route('owner.payments.verify', $paymentB->id))->assertNotFound();
        $this->patch(route('owner.payments.reject', $paymentB->id), ['reason' => 'tidak valid sekali'])->assertNotFound();
        $this->get(route('owner.payments.show', $paymentB->id))->assertNotFound();
        $this->assertSame(Payment::PENDING, $paymentB->fresh()->status);
    }

    public function test_daftar_dan_detail_pelanggan_owner_hanya_bisnis_sendiri(): void
    {
        $this->actingAs($this->ownerA);
        $this->get(route('owner.customers.index'))->assertOk()->assertSee($this->custA->name)->assertDontSee($this->custB->name);
        $this->get(route('owner.customers.show', $this->custB->id))->assertNotFound();
        $this->get(route('owner.customers.show', $this->custA->id))->assertOk();
    }

    public function test_daftar_booking_owner_hanya_milik_bisnisnya(): void
    {
        $this->actingAs($this->ownerA)->get(route('owner.bookings.index'))
            ->assertSee($this->bookingA->invoice_number)->assertDontSee($this->bookingB->invoice_number);
    }

    public function test_laporan_owner_hanya_menghitung_bisnis_sendiri_dan_admin_menghitung_semua(): void
    {
        foreach ([[$this->bookingA, 100000], [$this->bookingB, 250000]] as [$b, $amt]) {
            $b->payment->update(['status' => Payment::PAID, 'paid_at' => now(), 'proof_path' => 'x.png']);
        }

        $this->actingAs($this->ownerA)->get(route('owner.reports'))
            ->assertOk()->assertSee('Rp 100.000')->assertDontSee('Rp 350.000')->assertDontSee('Rp 250.000');

        $this->actingAs($this->makeUser('admin'))->get(route('admin.reports'))
            ->assertOk()->assertSee('Rp 350.000');
    }

    public function test_customer_tidak_bisa_mengakses_hewan_atau_booking_customer_lain(): void
    {
        $this->actingAs($this->custA);
        $this->get(route('customer.pets.edit', $this->petB->id))->assertNotFound();
        $this->put(route('customer.pets.update', $this->petB->id), ['name' => 'Hack', 'species' => 'Anjing', 'gender' => 'Jantan'])->assertNotFound();
        $this->delete(route('customer.pets.destroy', $this->petB->id))->assertNotFound();
        $this->assertSame('PetB', $this->petB->fresh()->name);
        $this->get(route('customer.bookings.show', $this->bookingB->id))->assertNotFound();
        $this->get(route('customer.bookings.pay', $this->bookingB->id))->assertNotFound();
    }

    public function test_booking_ke_dua_bisnis_berbeda_oleh_satu_customer_berhasil(): void
    {
        $this->actingAs($this->custA)->post(route('customer.bookings.store'), $this->bookingPayload($this->bizB, $this->petA, [$this->svcB->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::where('customer_id', $this->custA->id)->count());
        $this->assertSame(2, Booking::where('customer_id', $this->custA->id)->distinct()->count('business_id'));
    }

    public function test_bukti_pembayaran_hanya_dapat_dilihat_pihak_berhak(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('local')->put('payment-proofs/a.png', $png);
        $pay = $this->bookingA->payment;
        $pay->update(['status' => Payment::PENDING, 'proof_path' => 'payment-proofs/a.png']);

        $this->actingAs($this->custA)->get(route('payments.proof', $pay->id))->assertOk();
        $this->actingAs($this->ownerA)->get(route('payments.proof', $pay->id))->assertOk();
        $this->actingAs($this->makeUser('admin'))->get(route('payments.proof', $pay->id))->assertOk();
        $this->actingAs($this->custB)->get(route('payments.proof', $pay->id))->assertNotFound();
        $this->actingAs($this->ownerB)->get(route('payments.proof', $pay->id))->assertNotFound();
    }
}
