<?php

namespace App\Modules\Jeni\BookingCalendar\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Modules\Jeni\BookingCalendar\Services\PreorderPolicyService;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCalendarController extends Controller
{
    public function index(
        Request $request,
        SlotAvailabilityService $slots,
        PreorderPolicyService $preorders
    ): View {
        $validated = $request->validate([
            'business' => ['nullable', 'integer', 'min:1'],
            'date' => ['nullable', 'date'],
            'service' => ['nullable', 'integer', 'min:1'],
        ]);

        $businesses = Business::active()->orderBy('business_name')->get(['id', 'business_name']);
        $business = null;
        $services = collect();
        $slotInfo = null;
        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'])->toDateString()
            : now()->addDay()->toDateString();
        $serviceId = (int) ($validated['service'] ?? 0);

        if (isset($validated['business'])) {
            $business = Business::active()->findOrFail((int) $validated['business']);
            $services = $business->services()->active()->orderBy('name')->get();
            $selectedService = $serviceId > 0 ? $services->firstWhere('id', $serviceId) : null;
            abort_if($serviceId > 0 && ! $selectedService, 404);
            $duration = $selectedService ? max(15, (int) ($selectedService->duration_minutes ?: 60)) : 60;

            $info = $slots->getDailySlots($business, $date, $duration);
            $slotInfo = $preorders->displaySlots($business, $date, $info);
        }

        return view('jeni-booking::customer-calendar', compact(
            'businesses', 'business', 'services', 'slotInfo', 'date', 'serviceId'
        ));
    }
}
