<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SlotAvailabilityService
{
    /**
     * Hitung total durasi booking (dalam menit) dari daftar layanan.
     * Untuk layanan satuan 'hari' (Penitipan), slot check-in memakai durasi dasar layanan (default 60 menit).
     *
     * @param  iterable<\App\Models\Service>  $services
     * @param  array<int,int>  $quantities
     */
    public function calculateDurationMinutes(iterable $services, array $quantities): int
    {
        $totalMinutes = 0;

        foreach ($services as $service) {
            $base = max(15, (int) ($service->duration_minutes ?: 60));
            $qty = max(1, (int) ($quantities[$service->id] ?? 1));

            if ($service->unit === 'hari') {
                $totalMinutes += $base;
            } else {
                $totalMinutes += $base * $qty;
            }
        }

        return max(30, min(480, $totalMinutes ?: 60));
    }

    /**
     * Hitung jam selesai (HH:MM) dari jam mulai dan durasi menit.
     */
    public function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = min(23 * 60 + 59, $startMinutes + max(15, $durationMinutes));

        return $this->minutesToTime($endMinutes);
    }

    /**
     * Validasi ketersediaan jadwal operasional, hari tutup, jam istirahat, dan kapasitas slot.
     * Dipanggil di dalam transaksi database saat booking disimpan.
     */
    public function assertSlotAvailable(
        Business $business,
        string $dateString,
        string $bookingTime,
        int $durationMinutes = 60,
        ?int $ignoreBookingId = null
    ): array {
        $bookingDate = Carbon::parse($dateString);
        $time = substr($bookingTime, 0, 5);

        // 1. Periksa hari libur khusus bisnis.
        $isHoliday = $business->closures()
            ->whereDate('closed_date', $bookingDate->toDateString())
            ->exists();

        if ($isHoliday) {
            throw ValidationException::withMessages([
                'booking_date' => 'Bisnis tutup pada tanggal yang kamu pilih.',
            ]);
        }

        // 2. Cari jadwal rutin untuk hari yang dipilih (ISO weekday: Senin = 1, Minggu = 7).
        $dayOfWeek = $bookingDate->isoWeekday();
        $hours = $business->operatingHours()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $hours) {
            throw ValidationException::withMessages([
                'booking_date' => 'Jadwal operasional bisnis belum diatur. Silakan pilih bisnis lain atau hubungi pemilik bisnis.',
            ]);
        }

        // 3. Tolak booking pada hari yang ditandai tutup.
        if ($hours->is_closed) {
            throw ValidationException::withMessages([
                'booking_date' => 'Bisnis tutup pada hari yang dipilih.',
            ]);
        }

        // 4. Pastikan jam booking masuk dalam jam operasional.
        $opensAt = substr((string) $hours->opens_at, 0, 5);
        $closesAt = substr((string) $hours->closes_at, 0, 5);

        if ($time < $opensAt || $time >= $closesAt) {
            throw ValidationException::withMessages([
                'booking_time' => "Jam layanan harus berada dalam jam operasional {$opensAt}–{$closesAt}.",
            ]);
        }

        $startMin = $this->timeToMinutes($time);
        $closeMin = $this->timeToMinutes($closesAt);
        $endMin = min($closeMin, $startMin + max(15, $durationMinutes));

        // 5. Periksa waktu istirahat bisnis jika diatur.
        if (filled($hours->break_starts_at) && filled($hours->break_ends_at)) {
            $breakStart = substr((string) $hours->break_starts_at, 0, 5);
            $breakEnd = substr((string) $hours->break_ends_at, 0, 5);
            $bStartMin = $this->timeToMinutes($breakStart);
            $bEndMin = $this->timeToMinutes($breakEnd);

            if ($bEndMin > $bStartMin && $startMin < $bEndMin && $endMin > $bStartMin) {
                throw ValidationException::withMessages([
                    'booking_time' => "Jam layanan bertabrakan dengan waktu istirahat bisnis ({$breakStart}–{$breakEnd}).",
                ]);
            }
        }

        // 6. Periksa kapasitas slot terhadap booking aktif (tidak termasuk yang dibatalkan).
        $capacity = max(1, (int) ($hours->slot_capacity ?: 5));
        $overlappingCount = $this->countOverlappingActiveBookings(
            $business->id,
            $bookingDate->toDateString(),
            $startMin,
            $endMin,
            $ignoreBookingId
        );

        if ($overlappingCount >= $capacity) {
            throw ValidationException::withMessages([
                'booking_time' => "Slot pada jam {$time} sudah penuh (kapasitas maksimal {$capacity} booking). Silakan pilih jam lain.",
            ]);
        }

        return [
            'booking_end_time' => $this->minutesToTime($endMin),
            'duration_minutes' => max(15, $endMin - $startMin),
            'capacity' => $capacity,
            'used' => $overlappingCount + 1,
        ];
    }

    /**
     * Daftar seluruh slot pada tanggal tertentu untuk ditampilkan ke Pelanggan atau Kalender Owner.
     */
    public function getDailySlots(Business $business, string $dateString, int $requestedDuration = 60): array
    {
        $date = Carbon::parse($dateString);
        $dateStr = $date->toDateString();

        $closure = $business->closures()->whereDate('closed_date', $dateStr)->first();
        if ($closure) {
            return [
                'is_open' => false,
                'reason' => 'Bisnis libur khusus'.($closure->reason ? ": {$closure->reason}" : '.'),
                'slots' => [],
            ];
        }

        $hours = $business->operatingHours()->where('day_of_week', $date->isoWeekday())->first();
        if (! $hours) {
            return [
                'is_open' => false,
                'reason' => 'Jadwal operasional belum diatur.',
                'slots' => [],
            ];
        }

        if ($hours->is_closed) {
            return [
                'is_open' => false,
                'reason' => 'Bisnis tutup rutin pada hari ini.',
                'slots' => [],
            ];
        }

        $openMin = $this->timeToMinutes(substr((string) $hours->opens_at, 0, 5));
        $closeMin = $this->timeToMinutes(substr((string) $hours->closes_at, 0, 5));
        $step = max(15, (int) ($hours->slot_duration_minutes ?: 60));
        $capacity = max(1, (int) ($hours->slot_capacity ?: 5));

        $breakStartMin = filled($hours->break_starts_at) ? $this->timeToMinutes(substr((string) $hours->break_starts_at, 0, 5)) : null;
        $breakEndMin = filled($hours->break_ends_at) ? $this->timeToMinutes(substr((string) $hours->break_ends_at, 0, 5)) : null;

        $activeBookings = Booking::query()
            ->where('business_id', $business->id)
            ->whereDate('booking_date', $dateStr)
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->get(['id', 'booking_time', 'booking_end_time', 'duration_minutes']);

        $slots = [];
        for ($cursor = $openMin; $cursor < $closeMin; $cursor += $step) {
            $slotEnd = min($closeMin, $cursor + max($step, $requestedDuration));
            $timeStr = $this->minutesToTime($cursor);
            $endStr = $this->minutesToTime(min($closeMin, $cursor + $step));

            $isBreak = $breakStartMin !== null
                && $breakEndMin !== null
                && $breakEndMin > $breakStartMin
                && $cursor < $breakEndMin
                && $slotEnd > $breakStartMin;

            $used = 0;
            foreach ($activeBookings as $b) {
                [$bStart, $bEnd] = $this->bookingIntervalMinutes($b);
                if ($cursor < $bEnd && $slotEnd > $bStart) {
                    $used++;
                }
            }

            $remaining = $isBreak ? 0 : max(0, $capacity - $used);

            $slots[] = [
                'time' => $timeStr,
                'end_time' => $endStr,
                'used' => $used,
                'capacity' => $capacity,
                'remaining' => $remaining,
                'is_break' => $isBreak,
                'available' => ! $isBreak && $remaining > 0,
            ];
        }

        return [
            'is_open' => true,
            'reason' => null,
            'opens_at' => substr((string) $hours->opens_at, 0, 5),
            'closes_at' => substr((string) $hours->closes_at, 0, 5),
            'break_starts_at' => $hours->break_starts_at ? substr((string) $hours->break_starts_at, 0, 5) : null,
            'break_ends_at' => $hours->break_ends_at ? substr((string) $hours->break_ends_at, 0, 5) : null,
            'slot_duration_minutes' => $step,
            'slot_capacity' => $capacity,
            'slots' => $slots,
        ];
    }

    private function countOverlappingActiveBookings(
        int $businessId,
        string $dateString,
        int $startMin,
        int $endMin,
        ?int $ignoreBookingId = null
    ): int {
        $bookings = Booking::query()
            ->where('business_id', $businessId)
            ->whereDate('booking_date', $dateString)
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->when($ignoreBookingId !== null, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->get(['id', 'booking_time', 'booking_end_time', 'duration_minutes']);

        $count = 0;
        foreach ($bookings as $b) {
            [$bStart, $bEnd] = $this->bookingIntervalMinutes($b);
            if ($startMin < $bEnd && $endMin > $bStart) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function bookingIntervalMinutes(Booking $booking): array
    {
        $start = $this->timeToMinutes(substr((string) $booking->booking_time, 0, 5));
        if ($booking->booking_end_time) {
            $end = $this->timeToMinutes(substr((string) $booking->booking_end_time, 0, 5));
            if ($end > $start) {
                return [$start, $end];
            }
        }

        $dur = max(15, (int) ($booking->duration_minutes ?: 60));

        return [$start, $start + $dur];
    }

    public function timeToMinutes(string $hhmm): int
    {
        [$h, $m] = array_pad(explode(':', substr(trim($hhmm), 0, 5)), 2, '0');

        return ((int) $h) * 60 + ((int) $m);
    }

    public function minutesToTime(int $minutes): string
    {
        $minutes = max(0, min(23 * 60 + 59, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
