<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pemantauan layanan, booking, dan pembayaran seluruh platform (read-only). */
class MonitorController extends Controller
{
    public function services(Request $request): View
    {
        $businessId = (int) $request->query('business', 0);

        $services = Service::query()
            ->with('business')
            ->when($businessId > 0, fn ($q) => $q->where('business_id', $businessId))
            ->orderBy('business_id')->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.services', ['services' => $services, 'businesses' => Business::orderBy('business_name')->get(['id', 'business_name']), 'businessId' => $businessId]);
    }

    public function bookings(Request $request): View
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $bookings = Booking::query()
            ->with(['customer', 'business', 'pet', 'payment'])
            ->when(in_array($status, Booking::STATUSES, true), fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where('invoice_number', 'like', "%{$q}%"))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings', compact('bookings', 'status', 'q'));
    }

    public function payments(Request $request): View
    {
        $status = $request->query('status');

        $payments = Payment::query()
            ->with(['booking.business', 'booking.customer'])
            ->when(in_array($status, Payment::STATUSES, true), fn ($x) => $x->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payments', compact('payments', 'status'));
    }
}
