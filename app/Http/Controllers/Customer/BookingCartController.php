<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\User;
use App\Services\BookingCartService;
use App\Services\OrderCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingCartController extends Controller
{
    public function index(Request $request, BookingCartService $cart): View
    {
        $data = $cart->get($request->user());

        return view('customer.bookings.cart', [
            'groups' => array_values($data['groups']),
            'total' => $cart->total($data),
            'itemCount' => $cart->itemCount($data),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreBookingRequest $request, BookingCartService $cart): RedirectResponse
    {
        $cart->add($request->user(), $request->bookingData());

        return redirect()->route('customer.bookings.cart')
            ->with('success', 'Pilihan layanan berhasil ditambahkan ke keranjang booking.');
    }

    public function destroy(Request $request, string $group, BookingCartService $cart): RedirectResponse
    {
        $cart->removeGroup($request->user(), $group);

        return redirect()->route('customer.bookings.cart')
            ->with('success', 'Jadwal booking dihapus dari keranjang.');
    }

    public function checkout(Request $request, OrderCheckoutService $checkout): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
        ]);

        /** @var User $customer */
        $customer = $request->user();
        $order = $checkout->checkout($customer, $data['idempotency_key']);

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Checkout berhasil. Semua booking tersimpan dalam satu order.');
    }
}
