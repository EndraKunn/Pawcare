<?php

namespace App\Modules\Jeni\BookingCalendar\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Preorder is a real advance booking (not a waitlist). It reserves a slot immediately,
 * remains payable via the existing PawCare payment flow, and stays on the calendar.
 *
 * <= 30 days: ordinary booking. 31..pre_order_max_days: optional preorder.
 * A preorder may have a smaller capacity than the operating-hour slot capacity.
 */
class PreorderPolicyService
{
    public const REGULAR_DAYS = 30;
    public const MAX_PREORDER_DAYS = 365;

    public function policyFor(Business $business, string $dateString): array
    {
        $date = Carbon::parse($dateString)->startOfDay();
        $today = now()->startOfDay();
        $isPreorder = $date->greaterThan($today->copy()->addDays(self::REGULAR_DAYS));
        $enabled = (bool) $business->pre_order_enabled;
        $maxDays = min(self::MAX_PREORDER_DAYS, max(31, (int) ($business->pre_order_max_days ?: 180)));
        $capacity = min(100, max(1, (int) ($business->pre_order_slot_capacity ?: 5)));

        if ($date->lessThan($today)) {
            return ['allowed' => false, 'is_preorder' => false,
                'reason' => 'Tanggal booking tidak boleh di masa lalu.',
                'max_days' => $maxDays, 'capacity' => $capacity];
        }
        if ($isPreorder && ! $enabled) {
            return ['allowed' => false, 'is_preorder' => true,
                'reason' => 'Pre-order belum diaktifkan oleh bisnis ini. Pilih jadwal maksimal 30 hari ke depan.',
                'max_days' => $maxDays, 'capacity' => $capacity];
        }
        if ($isPreorder && $date->greaterThan($today->copy()->addDays($maxDays))) {
            return ['allowed' => false, 'is_preorder' => true,
                'reason' => "Pre-order hanya dibuka maksimal {$maxDays} hari ke depan.",
                'max_days' => $maxDays, 'capacity' => $capacity];
        }

        return ['allowed' => true, 'is_preorder' => $isPreorder,
            'reason' => null, 'max_days' => $maxDays, 'capacity' => $capacity];
    }

    /**
     * Run as Booking::creating event. BookingService::create and Atomic Checkout
     * both use Booking::create inside their DB transaction. Business lock prevents
     * overselling the smaller preorder cap against concurrent checkout requests.
     */
    public function validateAndMark(Booking $booking): void
    {
        if (! $booking->business_id || ! $booking->booking_date) {
            return;
        }

        $business = Business::query()->whereKey($booking->business_id)->lockForUpdate()->firstOrFail();
        $date = Carbon::parse($booking->booking_date)->toDateString();
        $policy = $this->policyFor($business, $date);
        if (! $policy['allowed']) {
            throw ValidationException::withMessages(['booking_date' => $policy['reason']]);
        }

        $booking->setAttribute('is_preorder', $policy['is_preorder']);

        $dayOfWeek = Carbon::parse($date)->isoWeekday();
        $hours = $business->operatingHours()->where('day_of_week', $dayOfWeek)->first();
        $operatingCapacity = max(1, (int) ($hours?->slot_capacity ?: 5));
        $capacity = $policy['is_preorder']
            ? min($operatingCapacity, $policy['capacity'])
            : $operatingCapacity;
        $slotService = app(SlotAvailabilityService::class);
        $start = $slotService->timeToMinutes((string) $booking->booking_time);
        $end = $booking->booking_end_time
            ? $slotService->timeToMinutes((string) $booking->booking_end_time)
            : $start + max(15, (int) ($booking->duration_minutes ?: 60));

        $overlapping = 0;
        $otherBookings = Booking::query()
            ->where('business_id', $business->id)
            ->whereDate('booking_date', $date)
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->get(['booking_time', 'booking_end_time', 'duration_minutes']);

        foreach ($otherBookings as $existing) {
            $existingStart = $slotService->timeToMinutes((string) $existing->booking_time);
            $existingEnd = $existing->booking_end_time
                ? $slotService->timeToMinutes((string) $existing->booking_end_time)
                : $existingStart + max(15, (int) ($existing->duration_minutes ?: 60));
            if ($start < $existingEnd && $end > $existingStart) {
                $overlapping++;
            }
        }

        if ($overlapping >= $capacity) {
            throw ValidationException::withMessages([
                'booking_time' => "Kapasitas slot ini sudah penuh (maksimal {$capacity} booking).",
            ]);
        }
    }

    /** Adjust the existing slot display so customer sees the actual preorder cap. */
    public function displaySlots(Business $business, string $dateString, array $slotInfo): array
    {
        $policy = $this->policyFor($business, $dateString);
        if (! $policy['allowed']) {
            return ['is_open' => false, 'reason' => $policy['reason'], 'slots' => [],
                'is_preorder' => $policy['is_preorder']];
        }
        $slotInfo['is_preorder'] = $policy['is_preorder'];
        if (! $slotInfo['is_open'] || ! $policy['is_preorder']) {
            return $slotInfo;
        }
        $capacity = min((int) $slotInfo['slot_capacity'], $policy['capacity']);
        $slotInfo['slot_capacity'] = $capacity;
        foreach ($slotInfo['slots'] as &$slot) {
            $slot['capacity'] = $capacity;
            $slot['remaining'] = $slot['is_break'] ? 0 : max(0, $capacity - $slot['used']);
            $slot['available'] = ! $slot['is_break'] && $slot['remaining'] > 0;
        }
        unset($slot);

        return $slotInfo;
    }
}
