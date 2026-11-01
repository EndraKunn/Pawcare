<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    private $owner; private $biz; private $cust; private $pet; private $svc1; private $svc2;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->owner, $this->biz] = $this->makeOwnerWithBusiness('A');
        $this->cust = $this->makeUser();
        $this->pet = $this->makePet($this->cust);
        $this->svc1 = $this->makeService($this->biz, 85000);
        $this->svc2 = $this->makeService($this->biz, 120000);
    }

    public function test_total_dihitung_ulang_di_server_dan_invoice_unik(): void
    {
        $payload = $this->bookingPayload($this->biz, $this->pet, [$this->svc1->id, $this->svc2->id], [
            'qty' => [$this->svc1->id => 3, $this->svc2->id => 1],
            'total_price' => 1, // dicoba dipalsukan dari browser
        ]);
        $this->actingAs($this->cust)->post(route('customer.bookings.store'), $payload)->assertSessionHasNoErrors();

        $booking = Booking::firstOrFail();
        $this->assertEquals(85000 * 3 + 120000, $booking->total_price);
        $this->assertEquals($booking->total_price, $booking->payment->amount);
        $this->assertSame('Menunggu', $booking->status);
        $this->assertSame('Belum Dibayar', $booking->payment->status);
        $this->assertCount(2, $booking->items);
        $this->assertSame(1, BookingStatusLog::where('booking_id', $booking->id)->count());

        $this->actingAs($this->cust)->post(route('customer.bookings.store'), $payload);
        $this->assertSame(2, Booking::distinct()->count('invoice_number'));
    }

    public function test_booking_menolak_layanan_bisnis_lain(): void
    {
        [, $bizB] = $this->makeOwnerWithBusiness('B');
        $svcB = $this->makeService($bizB, 50000);

        $this->actingAs($this->cust)->post(route('customer.bookings.store'), $this->bookingPayload($this->biz, $this->pet, [$svcB->id]))
            ->assertSessionHasErrors('services.0');
        $this->assertSame(0, Booking::count());
    }

    public function test_booking_item_model_menolak_layanan_bisnis_lain(): void
    {
        [, $bizB] = $this->makeOwnerWithBusiness('B');
        $svcB = $this->makeService($bizB, 50000);
        $booking = $this->makeBooking($this->cust, $this->biz, $this->pet, [$this->svc1->id => 1]);

        $this->expectException(\InvalidArgumentException::class);
        BookingItem::create(['booking_id' => $booking->id, 'service_id' => $svcB->id, 'price' => 1, 'quantity' => 1]);
    }

    public function test_booking_menolak_tanggal_masa_lalu(): void
    {
        $this->actingAs($this->cust)->post(route('customer.bookings.store'), $this->bookingPayload($this->biz, $this->pet, [$this->svc1->id], [
            'booking_date' => now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('booking_date');
        $this->assertSame(0, Booking::count());
    }

    public function test_booking_menolak_tanpa_layanan_hewan_orang_lain_layanan_nonaktif_dan_bisnis_suspend(): void
    {
        $other = $this->makeUser();
        $otherPet = $this->makePet($other, 'Orang');
        $inactive = $this->makeService($this->biz, 10000, ['status' => false]);

        $this->actingAs($this->cust);
        $this->post(route('customer.bookings.store'), $this->bookingPayload($this->biz, $this->pet, []))->assertSessionHasErrors('services');
        $this->post(route('customer.bookings.store'), $this->bookingPayload($this->biz, $otherPet, [$this->svc1->id]))->assertSessionHasErrors('pet_id');
        $this->post(route('customer.bookings.store'), $this->bookingPayload($this->biz, $this->pet, [$inactive->id]))->assertSessionHasErrors('services.0');

        $this->biz->update(['status' => false]);
        $this->post(route('customer.bookings.store'), $this->bookingPayload($this->biz, $this->pet, [$this->svc1->id]))->assertSessionHasErrors('business_id');
        $this->assertSame(0, Booking::count());
    }

    public function test_harga_item_adalah_snapshot(): void
    {
        $booking = $this->makeBooking($this->cust, $this->biz, $this->pet, [$this->svc1->id => 2]);
        $this->svc1->update(['price' => 999999]);

        $this->assertEquals(85000, $booking->items()->first()->price);
        $this->assertEquals(170000, $booking->fresh()->total_price);
    }

    public function test_customer_tanpa_hewan_diarahkan_menambah_hewan(): void
    {
        $newCust = $this->makeUser();
        $this->actingAs($newCust)->get(route('customer.bookings.create', ['business' => $this->biz->id]))
            ->assertRedirect(route('customer.pets.create'));
    }

    public function test_owner_mengubah_status_dan_tercatat_di_log(): void
    {
        $booking = $this->makeBooking($this->cust, $this->biz, $this->pet, [$this->svc1->id => 1]);

        $this->actingAs($this->owner)->patch(route('owner.bookings.status', $booking->id), ['status' => 'Dikonfirmasi', 'note' => 'siap'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Dikonfirmasi', $booking->fresh()->status);
        $log = BookingStatusLog::where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertSame('Dikonfirmasi', $log->status);
        $this->assertSame($this->owner->id, $log->changed_by);
    }

    public function test_transisi_status_tidak_valid_ditolak(): void
    {
        $booking = $this->makeBooking($this->cust, $this->biz, $this->pet, [$this->svc1->id => 1]);
        $this->actingAs($this->owner)->patch(route('owner.bookings.status', $booking->id), ['status' => 'Selesai'])
            ->assertSessionHasErrors('status');
        $this->assertSame('Menunggu', $booking->fresh()->status);
    }

    public function test_customer_dapat_membatalkan_booking_menunggu(): void
    {
        $booking = $this->makeBooking($this->cust, $this->biz, $this->pet, [$this->svc1->id => 1]);
        $this->actingAs($this->cust)->patch(route('customer.bookings.cancel', $booking->id))->assertSessionHas('success');
        $this->assertSame('Dibatalkan', $booking->fresh()->status);
    }
}
