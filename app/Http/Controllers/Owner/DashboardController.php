<?php

namespace App\Http\Controllers\Owner;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends OwnerController
{
    public function index(Request $request, ReportService $reports): View
    {
        $business = $this->business($request);
        $businessIds = $this->businessIds($request);
        $today = now()->toDateString();

        $periodMeta = $reports->resolvePeriod($request, 'month');
        $from = $periodMeta['from'];
        $to = $periodMeta['to'];
        $summary = $reports->businessSummary($businessIds, $from, $to);

        $todayBookings = $this->bookings($request)->whereDate('booking_date', $today);

        // Booking yang akan datang (tanggal >= hari ini, status aktif)
        $upcoming = $this->bookings($request)
            ->with(['customer:id,name', 'pet:id,name,species', 'items.service:id,name'])
            ->whereDate('booking_date', '>=', $today)
            ->whereIn('status', [Booking::STATUS_WAITING, Booking::STATUS_CONFIRMED, Booking::STATUS_PROGRESS])
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->take(6)
            ->get();

        return view('owner.dashboard', [
            'business' => $business,
            'period' => $periodMeta['period'],
            'periodLabel' => $periodMeta['label'],
            'from' => $from,
            'to' => $to,
            's' => $summary,
            'todayCount' => (clone $todayBookings)->count(),
            'todayWaiting' => (clone $todayBookings)->where('status', Booking::STATUS_WAITING)->count(),
            'pendingPayments' => $this->payments($request)->where('status', Payment::PENDING)->count(),
            'inProgress' => $this->bookings($request)->where('status', Booking::STATUS_PROGRESS)->count(),
            'monthRevenue' => (float) $this->payments($request)->where('status', Payment::PAID)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'chart' => $summary['daily_revenue'],
            'bookingChart' => $summary['daily_bookings'],
            'todoWaiting' => $this->bookings($request)->with(['customer', 'items.service'])->where('status', Booking::STATUS_WAITING)->oldest('id')->first(),
            'todoPayment' => $this->payments($request)->with('booking.customer')->where('status', Payment::PENDING)->oldest('id')->first(),
            'todoProgress' => $this->bookings($request)->with(['customer', 'items.service'])->where('status', Booking::STATUS_PROGRESS)->oldest('id')->first(),
            'upcoming' => $upcoming,
            'recent' => $this->bookings($request)->with(['customer', 'pet', 'items.service', 'payment'])->latest('id')->take(5)->get(),
        ]);
    }
}
