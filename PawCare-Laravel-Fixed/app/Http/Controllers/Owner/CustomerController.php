<?php

namespace App\Http\Controllers\Owner;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends OwnerController
{
    public function index(Request $request): View
    {
        $owner = $request->user();
        $search = trim((string) $request->query('q', ''));

        $customers = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->whereHas('bookings', fn ($b) => $b->forOwner($owner))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->withCount(['bookings as bookings_here' => fn ($b) => $b->forOwner($owner)])
            ->withSum(['bookings as spent_here' => fn ($b) => $b->forOwner($owner)->where('status', '!=', Booking::STATUS_CANCELLED)], 'total_price')
            ->withMax(['bookings as last_booking_at' => fn ($b) => $b->forOwner($owner)], 'created_at')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('owner.customers.index', compact('customers', 'search'));
    }

    public function show(Request $request, int $customer): View
    {
        $owner = $request->user();

        // Hanya pelanggan yang pernah memesan di bisnis milik owner ini.
        $user = User::where('role', User::ROLE_CUSTOMER)
            ->whereHas('bookings', fn ($b) => $b->forOwner($owner))
            ->findOrFail($customer);

        $bookings = Booking::forOwner($owner)->where('customer_id', $user->id)
            ->with(['pet', 'items.service', 'payment'])->latest('id')->get();

        // Hewan yang tampil hanya yang dipakai pada booking di bisnis ini.
        $pets = $bookings->pluck('pet')->unique('id')->values();

        return view('owner.customers.show', compact('user', 'bookings', 'pets'));
    }
}
