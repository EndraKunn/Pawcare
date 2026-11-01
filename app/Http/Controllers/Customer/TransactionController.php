<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $paid = fn () => Payment::where('status', Payment::PAID)->whereHas('booking', fn ($b) => $b->forCustomer($user));

        return view('customer.transactions', [
            'payments' => $paid()->with('booking.business')->latest('paid_at')->paginate(10),
            'total' => (float) $paid()->sum('amount'),
            'count' => $paid()->count(),
            'thisMonth' => (float) $paid()->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'businessCount' => Booking::forCustomer($user)->distinct()->count('business_id'),
        ]);
    }
}
