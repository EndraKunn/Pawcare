<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Order;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCheckoutService
{
    public function __construct(
        private BookingCartService $cart,
        private BookingService $bookings,
        private SlotAvailabilityService $slots,
    ) {
    }

    /**
     * Checkout seluruh grup cart sebagai satu order induk dan beberapa booking turunan.
     * Parent order, booking, item, payment, dan log dibuat dalam satu transaksi DB.
     */
    public function checkout(User $customer, string $idempotencyKey): Order
    {
        $store = Cache::store();

        // Lock per pelanggan mencegah dua request checkout memproses cart yang sama bersamaan.
        return $store->lock('pawcare:checkout-lock:user:'.$customer->id, 120)
            ->block(15, function () use ($customer, $idempotencyKey) {
                // Cart tidak boleh ditambah/dihapus selama snapshot checkout diproses.
                return $this->cart->withLock($customer, function () use ($customer, $idempotencyKey) {
                    $existing = Order::forCustomer($customer)
                        ->where('idempotency_key', $idempotencyKey)
                        ->with(['bookings.business', 'bookings.pet', 'bookings.items.service', 'bookings.payment'])
                        ->first();

                    // Retry dengan key yang sama selalu mengembalikan order yang sama.
                    if ($existing) {
                        return $existing;
                    }

                    $cart = $this->cart->get($customer);
                    if (empty($cart['groups'])) {
                        throw ValidationException::withMessages([
                            'cart' => 'Keranjang booking masih kosong.',
                        ]);
                    }

                    $requestHash = hash('sha256', json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    try {
                        $order = DB::transaction(function () use ($customer, $idempotencyKey, $requestHash, $cart) {
                            $order = Order::create([
                                'order_number' => Order::generateOrderNumber(),
                                'customer_id' => $customer->id,
                                'idempotency_key' => $idempotencyKey,
                                'request_hash' => $requestHash,
                                'status' => Order::STATUS_PENDING_PAYMENT,
                                'total_amount' => 0,
                                'snapshot' => [],
                                'checked_out_at' => now(),
                            ]);

                            $orderTotal = 0.0;
                            $orderSnapshot = [
                                'version' => 1,
                                'order_number' => $order->order_number,
                                'checked_out_at' => now()->toIso8601String(),
                                'bookings' => [],
                            ];

                            $groups = array_values($cart['groups']);
                            usort($groups, fn ($a, $b) => strcmp((string) $a['id'], (string) $b['id']));

                            foreach ($groups as $group) {
                                $business = Business::query()
                                    ->whereKey($group['business_id'])
                                    ->where('status', true)
                                    ->lockForUpdate()
                                    ->first();

                                if (! $business) {
                                    throw ValidationException::withMessages([
                                        'cart' => 'Salah satu bisnis di keranjang sudah tidak tersedia. Hapus item tersebut lalu coba lagi.',
                                    ]);
                                }

                                $pet = Pet::query()
                                    ->whereKey($group['pet_id'])
                                    ->where('customer_id', $customer->id)
                                    ->whereNull('archived_at')
                                    ->lockForUpdate()
                                    ->first();

                                if (! $pet) {
                                    throw ValidationException::withMessages([
                                        'cart' => 'Salah satu hewan di keranjang tidak lagi aktif atau bukan milik akun Anda.',
                                    ]);
                                }

                                $serviceIds = array_map('intval', array_keys($group['items'] ?? []));
                                $services = Service::query()
                                    ->where('business_id', $business->id)
                                    ->where('status', true)
                                    ->whereIn('id', $serviceIds)
                                    ->orderBy('id')
                                    ->lockForUpdate()
                                    ->get()
                                    ->keyBy('id');

                                if ($serviceIds === [] || $services->count() !== count(array_unique($serviceIds))) {
                                    throw ValidationException::withMessages([
                                        'cart' => 'Ada layanan yang sudah tidak aktif atau tidak lagi dimiliki bisnis terkait.',
                                    ]);
                                }

                                // Harga di cart adalah snapshot saat ditambahkan. Jika berubah, sinkronkan
                                // cart dan minta pelanggan mengonfirmasi ulang sebelum membuat order.
                                $priceUpdates = [];
                                foreach ($services as $service) {
                                    $line = $group['items'][$service->id] ?? null;
                                    if (! $line) {
                                        throw ValidationException::withMessages(['cart' => 'Detail layanan pada keranjang tidak valid.']);
                                    }

                                    $currentPrice = round((float) $service->price, 2);
                                    $cartPrice = round((float) ($line['unit_price'] ?? -1), 2);
                                    if ($currentPrice !== $cartPrice) {
                                        $priceUpdates[$service->id] = [
                                            'name' => $service->name,
                                            'category' => $service->category,
                                            'unit' => $service->unit,
                                            'duration_minutes' => (int) ($service->duration_minutes ?: 60),
                                            'unit_price' => $currentPrice,
                                        ];
                                    }
                                }

                                if ($priceUpdates !== []) {
                                    $this->cart->refreshPricesLocked($customer, (string) $group['id'], $priceUpdates);
                                    throw ValidationException::withMessages([
                                        'cart' => 'Harga satu atau beberapa layanan berubah. Keranjang telah diperbarui; periksa total terbaru lalu checkout kembali.',
                                    ]);
                                }

                                $quantities = [];
                                foreach ($services as $service) {
                                    $quantity = (int) ($group['items'][$service->id]['quantity'] ?? 0);
                                    if ($quantity < 1 || $quantity > 365) {
                                        throw ValidationException::withMessages(['cart' => 'Jumlah layanan pada keranjang tidak valid.']);
                                    }
                                    $quantities[(int) $service->id] = $quantity;
                                }

                                $duration = $this->slots->calculateDurationMinutes($services, $quantities);
                                // Revalidasi kapasitas/slot di dalam transaksi setelah business row dikunci.
                                $this->slots->assertSlotAvailable(
                                    $business,
                                    $group['booking_date'],
                                    $group['booking_time'],
                                    $duration
                                );

                                $booking = $this->bookings->create($customer, [
                                    'business_id' => (int) $business->id,
                                    'pet_id' => (int) $pet->id,
                                    'booking_date' => $group['booking_date'],
                                    'booking_time' => $group['booking_time'],
                                    'notes' => $group['notes'] ?? null,
                                    'quantities' => $quantities,
                                ], $order);

                                $orderTotal += (float) $booking->total_price;
                                $serviceSnapshots = [];
                                foreach ($services as $service) {
                                    $quantity = $quantities[$service->id];
                                    $unitPrice = round((float) $service->price, 2);
                                    $serviceSnapshots[] = [
                                        'service_id' => (int) $service->id,
                                        'name' => $service->name,
                                        'category' => $service->category,
                                        'unit' => $service->unit,
                                        'quantity' => $quantity,
                                        'unit_price' => $unitPrice,
                                        'subtotal' => round($unitPrice * $quantity, 2),
                                        'duration_minutes' => (int) ($service->duration_minutes ?: 60),
                                    ];
                                }

                                $orderSnapshot['bookings'][] = [
                                    'booking_id' => (int) $booking->id,
                                    'invoice_number' => $booking->invoice_number,
                                    'business_id' => (int) $business->id,
                                    'business_name' => $business->business_name,
                                    'pet_id' => (int) $pet->id,
                                    'pet_name' => $pet->name,
                                    'booking_date' => $group['booking_date'],
                                    'booking_time' => $group['booking_time'],
                                    'booking_end_time' => $booking->booking_end_time,
                                    'duration_minutes' => $booking->duration_minutes,
                                    'notes' => $group['notes'] ?? null,
                                    'total_price' => round((float) $booking->total_price, 2),
                                    'services' => $serviceSnapshots,
                                ];
                            }

                            $order->update([
                                'total_amount' => round($orderTotal, 2),
                                'snapshot' => $orderSnapshot,
                            ]);

                            return $order->load([
                                'bookings.business',
                                'bookings.pet',
                                'bookings.items.service',
                                'bookings.payment',
                            ]);
                        }, 3);
                    } catch (QueryException $exception) {
                        // Unique index customer_id + idempotency_key menjadi pagar terakhir
                        // bila request identik tiba dari beberapa worker/server sekaligus.
                        $existing = Order::forCustomer($customer)
                            ->where('idempotency_key', $idempotencyKey)
                            ->with(['bookings.business', 'bookings.pet', 'bookings.items.service', 'bookings.payment'])
                            ->first();

                        if ($existing) {
                            return $existing;
                        }

                        throw $exception;
                    }

                    // Cart hanya dihapus setelah seluruh transaksi database berhasil commit.
                    $this->cart->clear($customer);

                    return $order;
                });
            });
    }
}
