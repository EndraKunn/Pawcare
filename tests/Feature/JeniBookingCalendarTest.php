<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class JeniBookingCalendarTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_only_owner_can_manage_his_own_preorder_settings(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $customer = $this->makeUser();

        $this->actingAs($customer)->get(route('owner.jeni.preorder.edit'))->assertForbidden();
        $this->actingAs($owner)->get(route('owner.jeni.preorder.edit'))
            ->assertOk()->assertSee('Pre-order booking');
        $this->actingAs($owner)->put(route('owner.jeni.preorder.update'), [
            'pre_order_enabled' => 1,
            'pre_order_max_days' => 120,
            'pre_order_slot_capacity' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $business->fresh()->pre_order_enabled);
        $this->assertSame(120, (int) $business->fresh()->pre_order_max_days);
        $this->assertSame(2, (int) $business->fresh()->pre_order_slot_capacity);
    }

    public function test_invalid_preorder_setting_is_rejected(): void
    {
        [$owner] = $this->makeOwnerWithBusiness();
        $this->actingAs($owner)->put(route('owner.jeni.preorder.update'), [
            'pre_order_enabled' => 1,
            'pre_order_max_days' => 500,
            'pre_order_slot_capacity' => 0,
        ])->assertSessionHasErrors(['pre_order_max_days', 'pre_order_slot_capacity']);
    }

    public function test_preorder_is_disabled_by_default_and_existing_booking_still_works(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business);

        $normal = $this->makeBooking($customer, $business, $pet, [$service->id => 1]);
        $this->assertSame(0, (int) $normal->fresh()->is_preorder);

        $this->actingAs($customer)->post(route('customer.bookings.store'), $this->bookingPayload(
            $business, $pet, [$service->id], [
                'booking_date' => now()->addDays(45)->toDateString(),
            ]
        ))->assertSessionHasErrors('booking_date');
    }

    public function test_enabled_preorder_creates_flagged_booking_with_existing_payment(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $business->forceFill(['pre_order_enabled' => true, 'pre_order_max_days' => 180,
            'pre_order_slot_capacity' => 2])->save();
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 125000);

        $this->actingAs($customer)->post(route('customer.bookings.store'), $this->bookingPayload(
            $business, $pet, [$service->id], [
                'booking_date' => now()->addDays(45)->toDateString(),
            ]
        ))->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->firstOrFail();
        $this->assertSame(1, (int) $booking->is_preorder);
        $this->assertSame(125000.0, (float) $booking->total_price);
        $this->assertNotNull($booking->payment);
    }

    public function test_preorder_capacity_prevents_second_overlapping_booking(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $business->forceFill(['pre_order_enabled' => true, 'pre_order_max_days' => 180,
            'pre_order_slot_capacity' => 1])->save();
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business);
        $payload = $this->bookingPayload($business, $pet, [$service->id], [
            'booking_date' => now()->addDays(50)->toDateString(),
            'booking_time' => '10:00',
        ]);

        $this->actingAs($customer)->post(route('customer.bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('customer.bookings.store'), $payload)
            ->assertSessionHasErrors('booking_time');
        $this->assertSame(1, Booking::count());
    }

    public function test_preorder_outside_maximum_days_is_rejected(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $business->forceFill(['pre_order_enabled' => true, 'pre_order_max_days' => 60,
            'pre_order_slot_capacity' => 5])->save();
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business);

        $this->actingAs($customer)->post(route('customer.bookings.store'), $this->bookingPayload(
            $business, $pet, [$service->id], [
                'booking_date' => now()->addDays(70)->toDateString(),
            ]
        ))->assertSessionHasErrors('booking_date');
    }

    public function test_customer_calendar_is_scoped_to_active_businesses(): void
    {
        [, $active] = $this->makeOwnerWithBusiness('Aktif');
        [, $inactive] = $this->makeOwnerWithBusiness('Nonaktif');
        $inactive->update(['status' => false]);
        $customer = $this->makeUser();

        $this->actingAs($customer)->get(route('customer.jeni.calendar.index'))
            ->assertOk()->assertSee($active->business_name)
            ->assertDontSee($inactive->business_name);
        $this->actingAs($customer)->get(route('customer.jeni.calendar.index', [
            'business' => $inactive->id,
        ]))->assertNotFound();
    }

    public function test_owner_calendar_shows_preorder_badge(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $business->forceFill(['pre_order_enabled' => true, 'pre_order_max_days' => 180,
            'pre_order_slot_capacity' => 3])->save();
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business);
        $date = now()->addDays(44)->toDateString();
        $this->actingAs($customer)->post(route('customer.bookings.store'), $this->bookingPayload(
            $business, $pet, [$service->id], ['booking_date' => $date]
        ))->assertSessionHasNoErrors();

        $this->actingAs($owner)->get(route('owner.calendar.index', ['mode' => 'day', 'date' => $date]))
            ->assertOk()->assertSee('Pre-order');
    }
}
