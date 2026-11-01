<?php

namespace App\Http\Controllers\Owner;

use App\Models\Booking;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends OwnerController
{
    public function index(Request $request, SlotAvailabilityService $slotService): View
    {
        $business = $this->business($request);
        $viewMode = (string) $request->query('mode', 'week');
        if (! in_array($viewMode, ['day', 'week', 'month'], true)) {
            $viewMode = 'week';
        }

        $dateParam = (string) $request->query('date', now()->toDateString());
        try {
            $anchorDate = Carbon::parse($dateParam)->startOfDay();
        } catch (\Throwable) {
            $anchorDate = now()->startOfDay();
        }

        [$rangeStart, $rangeEnd] = match ($viewMode) {
            'day' => [$anchorDate->copy(), $anchorDate->copy()],
            'month' => [$anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()],
            default => [$anchorDate->copy()->startOfWeek(Carbon::MONDAY), $anchorDate->copy()->endOfWeek(Carbon::SUNDAY)],
        };

        $serviceId = (int) $request->query('service_id', 0);
        $statusFilter = (string) $request->query('status', '');
        if (! in_array($statusFilter, Booking::STATUSES, true)) {
            $statusFilter = '';
        }

        $services = $this->services($request)->orderBy('name')->get(['id', 'name', 'category', 'duration_minutes']);

        // Kalender dan ringkasan slot harus memakai bisnis yang sama.
        // $business diambil dari primaryBusiness() milik owner yang sedang login,
        // jadi pembatasan ini tetap aman dari akses lintas-tenant.
        $bookings = Booking::query()
            ->where('business_id', $business->id)
            ->with(['customer:id,name,phone', 'pet:id,name,species', 'items.service:id,name', 'payment'])
            // Gunakan whereDate agar filter konsisten pada SQLite dan driver database lain.
            ->whereDate('booking_date', '>=', $rangeStart->toDateString())
            ->whereDate('booking_date', '<=', $rangeEnd->toDateString())
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($serviceId > 0, fn ($q) => $q->whereHas('items', fn ($i) => $i->where('service_id', $serviceId)))
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->get();

        $bookingsByDate = $bookings->groupBy(fn (Booking $b) => $b->booking_date->toDateString());

        $days = [];
        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            $dStr = $cursor->toDateString();
            $days[] = [
                'date' => $cursor->copy(),
                'date_string' => $dStr,
                'bookings' => $bookingsByDate->get($dStr, collect()),
            ];
            $cursor->addDay();
        }

        $dailySlotInfo = $slotService->getDailySlots($business, $anchorDate->toDateString());

        $prevDate = match ($viewMode) {
            'day' => $anchorDate->copy()->subDay()->toDateString(),
            'month' => $anchorDate->copy()->subMonth()->toDateString(),
            default => $anchorDate->copy()->subWeek()->toDateString(),
        };

        $nextDate = match ($viewMode) {
            'day' => $anchorDate->copy()->addDay()->toDateString(),
            'month' => $anchorDate->copy()->addMonth()->toDateString(),
            default => $anchorDate->copy()->addWeek()->toDateString(),
        };

        return view('owner.calendar.index', [
            'business' => $business,
            'viewMode' => $viewMode,
            'anchorDate' => $anchorDate,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'services' => $services,
            'serviceId' => $serviceId,
            'statusFilter' => $statusFilter,
            'bookings' => $bookings,
            'days' => $days,
            'dailySlotInfo' => $dailySlotInfo,
        ]);
    }
}
