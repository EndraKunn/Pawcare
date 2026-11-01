<?php

namespace Tests\Concerns;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;

trait CreatesPawCareData
{
    private int $seq = 0;

    protected function makeUser(string $role = User::ROLE_CUSTOMER, array $attrs = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => $role.$this->seq.'@example.test',
            'phone' => '0812000000'.$this->seq,
            'password' => 'password',
            'role' => $role,
            'status' => true,
        ], $attrs));
    }

    /** @return array{0:User,1:Business} */
    protected function makeOwnerWithBusiness(string $name = 'Bisnis'): array
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $business = Business::create([
            'owner_id' => $owner->id,
            'business_name' => $name.' '.$this->seq,
            'address' => 'Jl. Contoh No. 1',
            'status' => true,
        ]);

        // Isi jadwal operasional default (Senin=1 s.d. Minggu=7) agar memenuhi validasi BookingService.
        for ($day = 1; $day <= 7; $day++) {
            $business->operatingHours()->create([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '20:00',
            ]);
        }

        return [$owner, $business];
    }

    protected function makeService(Business $business, float $price = 100000, array $attrs = []): Service
    {
        $this->seq++;

        return Service::create(array_merge([
            'business_id' => $business->id,
            'name' => 'Layanan '.$this->seq,
            'category' => 'Grooming',
            'unit' => 'sesi',
            'price' => $price,
            'status' => true,
        ], $attrs));
    }

    protected function makePet(User $customer, string $name = 'Milo'): Pet
    {
        return Pet::create([
            'customer_id' => $customer->id,
            'name' => $name,
            'species' => 'Anjing',
            'breed' => 'Campuran',
            'age' => 24,
            'gender' => 'Jantan',
        ]);
    }

    /** @param array<int,int> $quantities service_id => qty */
    protected function makeBooking(User $customer, Business $business, Pet $pet, array $quantities): Booking
    {
        return app(BookingService::class)->create($customer, [
            'business_id' => $business->id,
            'pet_id' => $pet->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '10:00',
            'notes' => null,
            'quantities' => $quantities,
        ]);
    }

    protected function bookingPayload(Business $business, Pet $pet, array $serviceIds, array $extra = []): array
    {
        return array_merge([
            'business_id' => $business->id,
            'pet_id' => $pet->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '10:00',
            'services' => $serviceIds,
        ], $extra);
    }
}