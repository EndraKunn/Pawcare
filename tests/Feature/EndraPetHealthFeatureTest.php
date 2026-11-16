<?php

namespace Tests\Feature;

use App\Events\CareNotePublished;
use App\Models\Pet;
use App\Models\PetCareNote;
use App\Models\PetHealthRecord;
use App\Models\PetHealthSharingPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class EndraPetHealthFeatureTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    private function validPng(string $name = 'vaksin.png'): UploadedFile
    {
        $bin = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $bin);
    }

    public function test_customer_can_store_pet_with_birth_date_and_weight_and_audit_is_recorded(): void
    {
        $customer = $this->makeUser();
        $birthDate = now()->subMonths(18)->toDateString();

        $this->actingAs($customer)
            ->post(route('customer.pets.store'), [
                'name' => 'Bobi',
                'species' => 'Anjing',
                'breed' => 'Beagle',
                'birth_date' => $birthDate,
                'weight' => 8.5,
                'gender' => 'Jantan',
            ])
            ->assertRedirect(route('customer.pets.index'));

        $pet = Pet::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('Bobi', $pet->name);
        $this->assertSame($birthDate, $pet->birth_date?->toDateString());
        $this->assertSame(8.5, $pet->weight);
        $this->assertGreaterThanOrEqual(17, (int) $pet->age);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pet.created',
            'subject_id' => $pet->id,
        ]);
    }

    public function test_pet_with_booking_can_be_archived_and_restored_with_audit_trail(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('Pet Hotel');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer, 'Kiko');
        $service = $this->makeService($business, 90000);
        $this->makeBooking($customer, $business, $pet, [$service->id => 1]);

        $this->actingAs($customer)
            ->patch(route('customer.pets.archive', $pet->id))
            ->assertRedirect(route('customer.pets.index'));

        $this->assertNotNull($pet->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pet.archived',
            'subject_id' => $pet->id,
        ]);

        $this->actingAs($customer)
            ->patch(route('customer.pets.restore', $pet->id))
            ->assertRedirect(route('customer.pets.index'));

        $this->assertNull($pet->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pet.restored',
            'subject_id' => $pet->id,
        ]);
    }

    public function test_granular_pet_health_sharing_permission_controls_business_access(): void
    {
        Storage::fake('local');
        [$ownerA, $businessA] = $this->makeOwnerWithBusiness('Klinik Alpha');
        [$ownerB, $businessB] = $this->makeOwnerWithBusiness('Klinik Beta');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer, 'Mochi');
        $serviceA = $this->makeService($businessA, 100000);
        $serviceB = $this->makeService($businessB, 100000);

        $this->makeBooking($customer, $businessA, $pet, [$serviceA->id => 1]);
        $this->makeBooking($customer, $businessB, $pet, [$serviceB->id => 1]);

        $this->actingAs($customer)
            ->post(route('customer.pets.health.store', $pet->id), [
                'record_type' => PetHealthRecord::TYPE_VACCINATION,
                'title' => 'Vaksin Rabies Tahunan',
                'recorded_date' => now()->subMonths(2)->toDateString(),
                'attachment' => $this->validPng('rabies.png'),
            ])
            ->assertSessionHas('success');

        $record = PetHealthRecord::firstOrFail();

        // Cabut izin khusus untuk Klinik Beta tetapi pertahankan untuk Klinik Alpha.
        $this->actingAs($customer)
            ->patch(route('customer.pets.health.permission', [$pet->id, $record->id]), [
                'business_id' => $businessB->id,
                'granted' => 0,
            ])
            ->assertSessionHas('success');

        $permB = PetHealthSharingPermission::where('pet_health_record_id', $record->id)
            ->where('business_id', $businessB->id)
            ->firstOrFail();
        $this->assertNotNull($permB->revoked_at);

        // Owner A tetap bisa mengakses lampiran, sedangkan Owner B ditolak (404).
        $this->actingAs($ownerA)->get(route('pets.health.attachment', $record->id))->assertOk();
        $this->actingAs($ownerB)->get(route('pets.health.attachment', $record->id))->assertNotFound();

        // Berikan kembali izin ke Klinik Beta -> Owner B dapat mengakses lampiran.
        $this->actingAs($customer)
            ->patch(route('customer.pets.health.permission', [$pet->id, $record->id]), [
                'business_id' => $businessB->id,
                'granted' => 1,
            ])
            ->assertSessionHas('success');

        $this->actingAs($ownerB)->get(route('pets.health.attachment', $record->id))->assertOk();
    }

    public function test_owner_storing_care_note_dispatches_realtime_event_and_records_audit(): void
    {
        Event::fake([CareNotePublished::class]);

        [$owner, $business] = $this->makeOwnerWithBusiness('Grooming House');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer, 'Snowy');
        $service = $this->makeService($business, 85000);
        $booking = $this->makeBooking($customer, $business, $pet, [$service->id => 1]);

        $this->actingAs($owner)
            ->post(route('owner.care-notes.store', $booking->id), [
                'care_summary' => 'Bulu sudah dirapikan dan telinga dibersihkan.',
                'recommendations' => 'Gunakan sampo hipoalergenik minggu depan.',
                'visible_to_customer' => 1,
            ])
            ->assertSessionHas('success');

        $note = PetCareNote::firstOrFail();
        Event::assertDispatched(CareNotePublished::class, fn (CareNotePublished $e) => $e->note->id === $note->id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pet.care_note.created',
            'subject_id' => $note->id,
        ]);
    }
}
