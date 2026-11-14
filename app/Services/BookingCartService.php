<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Cart booking disimpan pada cache store yang dikonfigurasi.
 * Set CACHE_STORE=redis untuk memakai Redis; file store tetap tersedia untuk demo lokal.
 */
class BookingCartService
{
    private const TTL_HOURS = 24;

    public function get(User $customer): array
    {
        $cart = Cache::store()->get($this->key($customer), ['groups' => []]);

        if (! is_array($cart) || ! isset($cart['groups']) || ! is_array($cart['groups'])) {
            return ['groups' => []];
        }

        return $cart;
    }

    /**
     * Tambahkan satu pilihan booking ke cart. Pilihan dengan bisnis, hewan, tanggal,
     * dan jam yang sama digabung menjadi satu booking turunan saat checkout.
     *
     * @param array{business_id:int,pet_id:int,booking_date:string,booking_time:string,notes:?string,quantities:array<int,int>} $data
     */
    public function add(User $customer, array $data): array
    {
        $business = Business::active()->find($data['business_id'] ?? null);
        if (! $business) {
            throw ValidationException::withMessages(['business_id' => 'Bisnis tidak tersedia.']);
        }

        $pet = Pet::ownedBy($customer)
            ->whereNull('archived_at')
            ->find($data['pet_id'] ?? null);
        if (! $pet) {
            throw ValidationException::withMessages(['pet_id' => 'Hewan tidak ditemukan pada akun Anda.']);
        }

        $quantities = $data['quantities'] ?? [];
        $serviceIds = array_map('intval', array_keys($quantities));
        $services = Service::active()
            ->where('business_id', $business->id)
            ->whereIn('id', $serviceIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($serviceIds === [] || $services->count() !== count(array_unique($serviceIds))) {
            throw ValidationException::withMessages([
                'services' => 'Pilih layanan aktif yang dimiliki bisnis tersebut.',
            ]);
        }

        $date = (string) ($data['booking_date'] ?? '');
        $time = substr((string) ($data['booking_time'] ?? ''), 0, 5);
        $groupId = substr(hash('sha256', implode('|', [
            $business->id,
            $pet->id,
            $date,
            $time,
        ])), 0, 24);

        $this->withLock($customer, function () use (
            $customer,
            $business,
            $pet,
            $services,
            $quantities,
            $date,
            $time,
            $groupId,
            $data
        ) {
            $store = Cache::store();
            $cart = $this->get($customer);
            $group = $cart['groups'][$groupId] ?? [
                'id' => $groupId,
                'business_id' => (int) $business->id,
                'business_name' => $business->business_name,
                'pet_id' => (int) $pet->id,
                'pet_name' => $pet->name,
                'pet_species' => $pet->species,
                'booking_date' => $date,
                'booking_time' => $time,
                'notes' => $data['notes'] ?? null,
                'items' => [],
            ];

            foreach ($services as $service) {
                $serviceId = (int) $service->id;
                $quantity = max(1, (int) ($quantities[$serviceId] ?? 1));
                $existingQuantity = (int) ($group['items'][$serviceId]['quantity'] ?? 0);
                $newQuantity = $existingQuantity + $quantity;

                if ($newQuantity > 365) {
                    throw ValidationException::withMessages([
                        'qty' => 'Jumlah layanan dalam keranjang maksimal 365.',
                    ]);
                }

                $group['items'][$serviceId] = [
                    'service_id' => $serviceId,
                    'name' => $service->name,
                    'category' => $service->category,
                    'unit' => $service->unit,
                    'duration_minutes' => (int) ($service->duration_minutes ?: 60),
                    'quantity' => $newQuantity,
                    'unit_price' => round((float) $service->price, 2),
                ];
            }

            if (array_key_exists('notes', $data)) {
                $group['notes'] = $data['notes'];
            }

            $cart['groups'][$groupId] = $group;
            $store->put($this->key($customer), $cart, now()->addHours(self::TTL_HOURS));
        });

        return $this->get($customer);
    }

    public function removeGroup(User $customer, string $groupId): void
    {
        $this->withLock($customer, function () use ($customer, $groupId) {
            $store = Cache::store();
            $cart = $this->get($customer);
            unset($cart['groups'][$groupId]);

            if ($cart['groups'] === []) {
                $store->forget($this->key($customer));
                return;
            }

            $store->put($this->key($customer), $cart, now()->addHours(self::TTL_HOURS));
        });
    }

    /** @param array<int,array{name?:string,category?:string,unit?:string,duration_minutes?:int,unit_price:float}> $updates */
    public function refreshPrices(User $customer, string $groupId, array $updates): void
    {
        $this->withLock($customer, fn () => $this->refreshPricesLocked($customer, $groupId, $updates));
    }

    /** Dipanggil saat checkout sudah memegang cart lock pelanggan. */
    public function refreshPricesLocked(User $customer, string $groupId, array $updates): void
    {
        $store = Cache::store();
        $cart = $this->get($customer);
        if (! isset($cart['groups'][$groupId])) {
            return;
        }

        foreach ($updates as $serviceId => $values) {
            if (! isset($cart['groups'][$groupId]['items'][$serviceId])) {
                continue;
            }

            $cart['groups'][$groupId]['items'][$serviceId] = array_merge(
                $cart['groups'][$groupId]['items'][$serviceId],
                $values
            );
        }

        $store->put($this->key($customer), $cart, now()->addHours(self::TTL_HOURS));
    }

    /** Jalankan callback sambil mencegah cart berubah di tengah checkout. */
    public function withLock(User $customer, callable $callback): mixed
    {
        return Cache::store()->lock($this->lockKey($customer), 120)->block(15, $callback);
    }

    public function clear(User $customer): void
    {
        Cache::store()->forget($this->key($customer));
    }

    public function total(array $cart): float
    {
        $total = 0.0;
        foreach ($cart['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $total += (float) $item['unit_price'] * (int) $item['quantity'];
            }
        }

        return round($total, 2);
    }

    public function itemCount(array $cart): int
    {
        $count = 0;
        foreach ($cart['groups'] ?? [] as $group) {
            $count += count($group['items'] ?? []);
        }

        return $count;
    }

    private function key(User $customer): string
    {
        return 'pawcare:booking-cart:user:'.$customer->id;
    }

    private function lockKey(User $customer): string
    {
        return 'pawcare:booking-cart-lock:user:'.$customer->id;
    }
}
