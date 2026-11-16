<?php

namespace Tests\Feature;

use App\Events\CatalogChanged;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class EndraCatalogInventoryFeatureTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_owner_crud_service_stores_capacity_and_dispatches_catalog_changed_event(): void
    {
        Event::fake([CatalogChanged::class]);

        [$owner, $business] = $this->makeOwnerWithBusiness('Happy Paws');
        $this->actingAs($owner);

        $this->post(route('owner.services.store'), [
            'name' => 'Grooming Spa Lengkap',
            'category' => 'Grooming',
            'unit' => 'sesi',
            'duration_minutes' => 90,
            'capacity' => 3,
            'price' => 125000,
            'description' => 'Mandi sehat dan potong kuku',
        ])->assertRedirect(route('owner.services.index'));

        $service = Service::where('business_id', $business->id)->firstOrFail();
        $this->assertSame(90, $service->duration_minutes);
        $this->assertSame(3, $service->capacity);
        $this->assertSame(125000, $service->price_rupiah);
        $this->assertTrue($service->is_available);

        Event::assertDispatched(CatalogChanged::class, function (CatalogChanged $event) use ($service) {
            return $event->serviceId === $service->id
                && $event->action === 'created'
                && $event->capacity === 3
                && $event->priceRupiah === 125000;
        });

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service.created',
            'subject_id' => $service->id,
        ]);

        // Toggle nonaktif menyiarkan CatalogChanged dengan status false.
        $this->patch(route('owner.services.toggle', $service->id))->assertRedirect();
        $this->assertFalse($service->fresh()->status);
        $this->assertFalse($service->fresh()->is_available);

        Event::assertDispatched(CatalogChanged::class, function (CatalogChanged $event) use ($service) {
            return $event->serviceId === $service->id
                && $event->action === 'toggled'
                && $event->status === false;
        });
    }

    public function test_service_with_booking_history_is_deactivated_and_broadcasts_deactivated_action(): void
    {
        Event::fake([CatalogChanged::class]);

        [$owner, $business] = $this->makeOwnerWithBusiness('Paw Care');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer, 'Milo');
        $service = $this->makeService($business, 80000);
        $this->makeBooking($customer, $business, $pet, [$service->id => 1]);

        $this->actingAs($owner)
            ->delete(route('owner.services.destroy', $service->id))
            ->assertRedirect();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => false,
        ]);

        Event::assertDispatched(CatalogChanged::class, function (CatalogChanged $event) use ($service) {
            return $event->serviceId === $service->id && $event->action === 'deactivated';
        });
    }

    public function test_catalog_availability_endpoint_respects_service_capacity_and_active_bookings(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('Griya Satwa');
        $customer1 = $this->makeUser();
        $customer2 = $this->makeUser();
        $pet1 = $this->makePet($customer1, 'Coco');
        $pet2 = $this->makePet($customer2, 'Lulu');

        $service = $this->makeService($business, 110000, [
            'name' => 'Penitipan VIP',
            'category' => 'Penitipan',
            'unit' => 'hari',
            'duration_minutes' => 60,
            'capacity' => 2,
        ]);

        $date = now()->addDays(3)->toDateString();

        // Buat 1 booking pada jam 10:00.
        $this->actingAs($customer1)
            ->post(route('customer.bookings.store'), $this->bookingPayload($business, $pet1, [$service->id], [
                'booking_date' => $date,
                'booking_time' => '10:00',
            ]))
            ->assertSessionHasNoErrors();

        $response = $this->getJson(route('catalog.availability', [
            'business' => $business->id,
            'date' => $date,
            'service_id' => $service->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('business_id', $business->id)
            ->assertJsonPath('service_id', $service->id)
            ->assertJsonPath('availability.is_open', true)
            ->assertJsonPath('availability.slot_capacity', 2);

        $slots = collect($response->json('availability.slots'));
        $slot10 = $slots->firstWhere('time', '10:00');
        $this->assertNotNull($slot10);
        $this->assertSame(1, $slot10['used']);
        $this->assertSame(1, $slot10['remaining']);
        $this->assertTrue($slot10['available']);
    }

    public function test_catalog_search_matches_service_name_and_supports_price_sorting(): void
    {
        [, $bizMurah] = $this->makeOwnerWithBusiness('Salon Hemat');
        $this->makeService($bizMurah, 50000, ['name' => 'Hydrotherapy Anjing']);

        [, $bizMahal] = $this->makeOwnerWithBusiness('Salon Sultan');
        $this->makeService($bizMahal, 250000, ['name' => 'Grooming Reguler']);

        // Pencarian berdasarkan nama layanan spesifik ("Hydrotherapy").
        $this->get(route('catalog', ['q' => 'Hydrotherapy']))
            ->assertOk()
            ->assertSee($bizMurah->business_name)
            ->assertDontSee($bizMahal->business_name);

        // Pengurutan harga termurah.
        $this->get(route('catalog', ['sort' => 'price_asc']))
            ->assertOk()
            ->assertSeeInOrder([$bizMurah->business_name, $bizMahal->business_name]);
    }
}
