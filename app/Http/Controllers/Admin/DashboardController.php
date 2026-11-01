<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $periodMeta = $reports->resolvePeriod($request, 'month');
        $from = $periodMeta['from'];
        $to = $periodMeta['to'];

        $summary = $reports->businessSummary(null, $from, $to);
        $perBusiness = $reports->perBusiness($from, $to);

        return view('admin.dashboard', [
            'period' => $periodMeta['period'],
            'periodLabel' => $periodMeta['label'],
            'from' => $from,
            'to' => $to,
            'totals' => $reports->platformTotals(),
            's' => $summary,
            'top' => $perBusiness->sortByDesc('revenue')->take(5),
            'mostActiveBusinesses' => $perBusiness->sortByDesc('bookings_count')->take(5),
            'totalBookings' => Booking::count(),
            'pendingPayments' => Payment::where('status', Payment::PENDING)->count(),
        ]);
    }
}
