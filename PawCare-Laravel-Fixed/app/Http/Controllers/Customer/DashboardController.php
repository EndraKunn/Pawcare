<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $active = Booking::forCustomer($user)->whereIn('status', [Booking::STATUS_WAITING, Booking::STATUS_CONFIRMED, Booking::STATUS_PROGRESS])->count();
        $inProgress = Booking::forCustomer($user)->where('status', Booking::STATUS_PROGRESS)->count();

        $needPay = Payment::whereHas('booking', fn ($b) => $b->forCustomer($user)->where('status', '!=', Booking::STATUS_CANCELLED))
            ->whereIn('status', [Payment::UNPAID, Payment::REJECTED]);
        $needPayCount = (clone $needPay)->count();
        $needPayTotal = (float) (clone $needPay)->sum('amount');
        $firstUnpaid = (clone $needPay)->with('booking')->latest('id')->first();

        $paid = Payment::whereHas('booking', fn ($b) => $b->forCustomer($user))->where('status', Payment::PAID);

        return view('customer.dashboard', [
            'user' => $user,
            'active' => $active,
            'inProgress' => $inProgress,
            'needPayCount' => $needPayCount,
            'needPayTotal' => $needPayTotal,
            'firstUnpaid' => $firstUnpaid,
            'petsCount' => $user->pets()->count(),
            'petNames' => $user->pets()->orderBy('name')->take(3)->pluck('name')->implode(', '),
            'paidTotal' => (float) (clone $paid)->sum('amount'),
            'paidCount' => (clone $paid)->count(),
            'recent' => Booking::forCustomer($user)->with(['business', 'pet', 'payment'])->latest('id')->take(4)->get(),
            'pets' => $user->pets()
    ->whereNull('archived_at')
    ->orderBy('name')
    ->get(),
        ]);
    }
}
