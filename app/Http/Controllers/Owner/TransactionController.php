<?php

namespace App\Http\Controllers\Owner;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends OwnerController
{
    public function index(Request $request): View
    {
        $paid = fn () => $this->payments($request)->where('status', Payment::PAID);

        $total = (float) $paid()->sum('amount');
        $count = $paid()->count();

        return view('owner.transactions', [
            'payments' => $paid()->with('booking.customer')->latest('paid_at')->paginate(10),
            'total' => $total,
            'count' => $count,
            'avg' => $count ? $total / $count : 0,
        ]);
    }
}
