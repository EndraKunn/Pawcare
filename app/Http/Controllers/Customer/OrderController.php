<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function show(Request $request, int $order): View
    {
        $model = Order::forCustomer($request->user())
            ->with([
                'bookings.business',
                'bookings.pet',
                'bookings.items.service',
                'bookings.payment',
            ])
            ->findOrFail($order);

        return view('customer.orders.show', ['order' => $model]);
    }
}
