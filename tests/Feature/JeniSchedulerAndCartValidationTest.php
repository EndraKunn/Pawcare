<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Services\BookingCartService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class JeniSchedulerAndCartValidationTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_scheduler_registers_release_scheduled_and_cleanup_exports_commands(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->implode("\n");

        $this->assertStringContainsString('ordering:release-scheduled', $events);
        $this->assertStringContainsString('reports:cleanup-expired', $events);
    }

    public function test_release_scheduled_command_dispatches_due_preorder_bookings(): void
    {
        Event::fake([BookingCreated::class]);

        [, $business] = $this->makeOwnerWithBusiness('Preorder Salon');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 95000);
        $booking = $this->makeBooking($customer, $business, $pet, [$service->id => 1]);

        $booking->forceFill([
            'is_preorder' => true,
            'booking_date' => now()->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ])->save();

        $this->artisan('ordering:release-scheduled', ['--days' => 1])
            ->assertExitCode(0);

        Event::assertDispatched(BookingCreated::class, fn (BookingCreated $e) => $e->booking->id === $booking->id);

        $this->artisan('reports:cleanup-expired')
            ->assertExitCode(0);
    }

    public function test_booking_and_cart_notes_enforce_max_200_characters_and_sanitize_html(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('Cat Hotel');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business, 80000);

        // Catatan > 200 karakter ditolak (FR-26).
        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.store'), $this->bookingPayload($business, $pet, [$service->id], [
                'notes' => str_repeat('a', 201),
            ]))
            ->assertSessionHasErrors('notes');

        // Catatan <= 200 karakter dengan tag HTML disanitasi bersih.
        $this->actingAs($customer)
            ->post(route('customer.bookings.cart.store'), $this->bookingPayload($business, $pet, [$service->id], [
                'notes' => '<b>Alergi</b> ayam &amp; ikan',
            ]))
            ->assertRedirect(route('customer.bookings.cart'));

        $cart = app(BookingCartService::class)->get($customer);
        $group = array_values($cart['groups'])[0] ?? null;
        $this->assertNotNull($group);
        $this->assertSame('Alergi ayam &amp; ikan', $group['notes']);
        $this->assertIsInt(app(BookingCartService::class)->total($cart));
        $this->assertSame(80000, app(BookingCartService::class)->total($cart));
    }
}
