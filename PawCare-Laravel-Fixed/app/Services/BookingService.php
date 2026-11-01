<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingStatusLog;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PawCareStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private ?SlotAvailabilityService $slotService = null
    ) {
        $this->slotService ??= new SlotAvailabilityService;
    }

    /**
     * Membuat booking. Seluruh validasi kepemilikan, ketersediaan slot, dan perhitungan harga
     * dilakukan di server di dalam transaksi database; total dari browser tidak dipercaya.
     *
     * @param  array{business_id:int,pet_id:int,booking_date:string,booking_time:string,notes:?string,quantities:array<int,int>}  $data
     */
    public function create(User $customer, array $data): Booking
    {
        return DB::transaction(function () use ($customer, $data) {
            $business = Business::active()->find($data['business_id']);
            if (! $business) {
                throw ValidationException::withMessages(['business_id' => 'Bisnis tidak tersedia.']);
            }

            $pet = Pet::ownedBy($customer)->whereNull('archived_at')->find($data['pet_id']);
            if (! $pet) {
                throw ValidationException::withMessages(['pet_id' => 'Hewan tidak ditemukan pada akun Anda.']);
            }

            $serviceIds = array_keys($data['quantities']);
            $services = Service::active()
                ->where('business_id', $business->id)
                ->whereIn('id', $serviceIds)
                ->get()
                ->keyBy('id');

            if (empty($serviceIds) || $services->count() !== count($serviceIds)) {
                throw ValidationException::withMessages([
                    'services' => 'Ada layanan yang tidak tersedia atau bukan milik bisnis yang dipilih.',
                ]);
            }

            $bookingTime = substr($data['booking_time'], 0, 5);
            $durationMinutes = $this->slotService->calculateDurationMinutes($services, $data['quantities']);

            // Validasi hari libur, jam operasional, waktu istirahat, dan kapasitas slot di dalam transaksi.
            $slotMeta = $this->slotService->assertSlotAvailable(
                $business,
                $data['booking_date'],
                $bookingTime,
                $durationMinutes
            );

            $booking = Booking::create([
                'invoice_number' => Booking::generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'business_id' => $business->id,
                'pet_id' => $pet->id,
                'booking_date' => $data['booking_date'],
                'booking_time' => $bookingTime,
                'booking_end_time' => $slotMeta['booking_end_time'],
                'duration_minutes' => $slotMeta['duration_minutes'],
                'status' => Booking::STATUS_WAITING,
                'total_price' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $total = 0.0;
            foreach ($services as $service) {
                $qty = max(1, (int) ($data['quantities'][$service->id] ?? 1));
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'service_id' => $service->id,
                    'price' => $service->price, // snapshot harga saat booking
                    'quantity' => $qty,
                ]);
                $total += $service->price * $qty;
            }

            $booking->update(['total_price' => $total]);

            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $total,
                'payment_method' => Payment::METHOD_MANUAL,
                'status' => Payment::UNPAID,
            ]);

            $this->log($booking, $customer, Booking::STATUS_WAITING, 'Booking dibuat oleh pelanggan');

            return $booking;
        });
    }

    /**
     * Mengubah status booking, mencatat histori di booking_status_logs,
     * dan mengirim notifikasi kepada Customer ketika status relevan berhasil disimpan.
     */
    public function changeStatus(
        Booking $booking,
        User $actor,
        string $newStatus,
        ?string $note = null
    ): Booking {
        if (! in_array($newStatus, $booking->allowedNextStatuses(), true)) {
            throw ValidationException::withMessages([
                'status' => "Status tidak dapat diubah dari {$booking->status} ke {$newStatus}.",
            ]);
        }

        return DB::transaction(function () use (
            $booking,
            $actor,
            $newStatus,
            $note
        ) {
            $previousStatus = $booking->status;

            $booking->update(['status' => $newStatus]);

            $this->log($booking, $actor, $newStatus, $note);

            $notifyStatuses = [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_DONE,
            ];

            if (
                $previousStatus !== $newStatus
                && in_array($newStatus, $notifyStatuses, true)
            ) {
                $booking->loadMissing('customer');
                $customer = $booking->customer;

                if ($customer) {
                    $title = match ($newStatus) {
                        Booking::STATUS_CONFIRMED => 'Booking dikonfirmasi',
                        Booking::STATUS_CANCELLED => 'Booking dibatalkan',
                        Booking::STATUS_DONE => 'Booking selesai',
                        default => 'Status booking diperbarui',
                    };

                    $message = "Booking {$booking->invoice_number} sekarang berstatus {$newStatus}.";
                    if (filled($note)) {
                        $message .= ' Catatan: '.trim((string) $note);
                    }

                    $targetUrl = route('customer.bookings.show', $booking->id);

                    // Cegah duplikasi notifikasi untuk invoice & status yang sama.
                    $alreadyNotified = $customer->notifications()
                        ->where('type', PawCareStatusNotification::class)
                        ->where('data', 'like', '%"'.$booking->invoice_number.'"%')
                        ->where('data', 'like', '%berstatus '.$newStatus.'.%')
                        ->exists();

                    if (! $alreadyNotified) {
                        $customer->notify(
                            new PawCareStatusNotification(
                                title: $title,
                                message: $message,
                                url: $targetUrl,
                            )
                        );
                    }
                }
            }

            return $booking;
        });
    }

    public function log(Booking $booking, User $actor, string $status, ?string $note = null): BookingStatusLog
    {
        return BookingStatusLog::create([
            'booking_id' => $booking->id,
            'changed_by' => $actor->id,
            'status' => $status,
            'note' => $note,
            'changed_at' => now(),
        ]);
    }
}
