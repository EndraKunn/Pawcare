<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * Basis controller Owner. SEMUA query data bisnis harus lewat helper di sini
 * agar Owner hanya dapat menjangkau bisnis miliknya sendiri (NFR-04, NFR-08).
 * Data milik bisnis lain -> findOrFail() menghasilkan 404.
 */
abstract class OwnerController extends Controller
{
    protected function business(Request $request): Business
    {
        $business = $request->user()->primaryBusiness();
        abort_unless($business, 403, 'Akun Anda belum memiliki bisnis.');

        return $business;
    }

    /** @return int[] */
    protected function businessIds(Request $request): array
    {
        return Business::ownedBy($request->user())->pluck('id')->all();
    }

    protected function bookings(Request $request)
    {
        return Booking::forOwner($request->user());
    }

    protected function services(Request $request)
    {
        return Service::forOwner($request->user());
    }

    protected function payments(Request $request)
    {
        return Payment::whereHas('booking.business', fn ($b) => $b->where('owner_id', $request->user()->id));
    }
}
