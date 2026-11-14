<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class AtomicCheckoutTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_cart_menggabungkan_layanan_dan_checkout_membuat_order_induk_booking_turunan(): void
    {
        [$ownerA, $businessA] = $this->makeOwnerWithBusiness('Hotel A');
        [$ownerB, $businessB] = $this->makeOwnerWithBusiness('Salon B');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $serviceA = $this->makeService($businessA, 85000, ['name' => 'Grooming A']);
        $serviceB = $this->makeService($businessB, 120000, ['name' => 'Penitipan B']);
        $date = now()->addDays(2)->toDateString();

        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.add'), $this->bookingPayload($businessA, $pet, [$serviceA->id], [
                'booking_date' => $date,
                'booking_time' => '10:00',
                'qty' => [$serviceA->id => 2],
            ]))
            ->assertRedirect(route('customer.bookings.cart'));

        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.add'), $this->bookingPayload($businessB, $pet, [$serviceB->id], [
                'booking_date' => $date,
                'booking_time' => '11:00',
                'qty' => [$serviceB->id => 1],
            ]))
            ->assertRedirect(route('customer.bookings.cart'));

        $this->actingAs($customer)
            ->get(route('customer.bookings.cart'))
            ->assertOk()
            ->assertSee('Grooming A')
            ->assertSee('Penitipan B');

        $key = (string) Str::uuid();
        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.checkout'), ['idempotency_key' => $key])
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(2, $order->bookings()->count());
        $this->assertSame(2, count($order->snapshot['bookings']));
        $this->assertEquals(290000, $order->total_amount);
        $this->assertSame(2, Booking::where('order_id', $order->id)->count());
        $this->assertSame(2, $order->bookings()->whereHas('payment')->count());

        // Retry dengan idempotency key yang sama tidak boleh membuat order/booking baru.
        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.checkout'), ['idempotency_key' => $key])
            ->assertRedirect(route('customer.orders.show', $order->id));

        $this->assertSame(1, Order::count());
        $this->assertSame(2, Booking::count());
    }

    public function test_checkout_menghentikan_proses_dan_memperbarui_cart_jika_harga_berubah(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('Harga Berubah');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 100000, ['name' => 'Grooming']);

        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.add'), $this->bookingPayload($business, $pet, [$service->id]))
            ->assertRedirect(route('customer.bookings.cart'));

        $service->update(['price' => 135000]);

        $this->actingAs($customer)
            ->from(route('customer.bookings.cart'))
            ->post(route('customer.bookings.cart.checkout'), ['idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('customer.bookings.cart'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Booking::count());
        $this->actingAs($customer)
            ->get(route('customer.bookings.cart'))
            ->assertOk()
            ->assertSee('135.000');
    }

    public function test_checkout_gagal_atomik_jika_salah_satu_slot_sudah_penuh(): void
    {
        [, $businessA] = $this->makeOwnerWithBusiness('Bisnis A');
        [, $businessB] = $this->makeOwnerWithBusiness('Bisnis B');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $serviceA = $this->makeService($businessA, 80000);
        $serviceB = $this->makeService($businessB, 90000);
        $date = now()->addDays(2)->toDateString();
        $day = Carbon::parse($date)->isoWeekday();

        $businessB->operatingHours()->where('day_of_week', $day)->update(['slot_capacity' => 1]);
        $this->makeBooking($customer, $businessB, $pet, [$serviceB->id => 1]);

        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.add'), $this->bookingPayload($businessA, $pet, [$serviceA->id], [
                'booking_date' => $date,
                'booking_time' => '12:00',
            ]))
            ->assertRedirect();

        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.add'), $this->bookingPayload($businessB, $pet, [$serviceB->id], [
                'booking_date' => $date,
                'booking_time' => '10:00',
            ]))
            ->assertRedirect();

        $this->actingAs($customer)
            ->from(route('customer.bookings.cart'))
            ->post(route('customer.bookings.cart.checkout'), ['idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('customer.bookings.cart'))
            ->assertSessionHasErrors('booking_time');

        // Hanya booking lama yang tetap ada; order dan booking dari checkout dibatalkan seluruhnya.
        $this->assertSame(0, Order::count());
        $this->assertSame(1, Booking::count());
    }

    public function test_cart_dan_order_hanya_dapat_dibuka_customer_pemiliknya(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('Bisnis Privat');
        $customerA = $this->makeUser();
        $customerB = $this->makeUser();
        $pet = $this->makePet($customerA);
        $service = $this->makeService($business, 70000);

        $this->actingAs($customerA)
            ->post(route('customer.bookings.cart.add'), $this->bookingPayload($business, $pet, [$service->id]))
            ->assertRedirect();
        $this->actingAs($customerA)
            ->post(route('customer.bookings.cart.checkout'), ['idempotency_key' => (string) Str::uuid()])
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->actingAs($customerB)
            ->get(route('customer.orders.show', $order->id))
            ->assertNotFound();
    }
}
