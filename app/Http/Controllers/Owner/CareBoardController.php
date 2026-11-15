<?php

namespace App\Http\Controllers\Owner;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CareBoardController extends OwnerController
{
    public function index(Request $request): View
    {
        $business = $this->business($request);
        $bookings = $this->bookings($request)->with([
            'customer:id,name,phone', 'pet:id,name,species', 'items.service:id,name',
            'payment:id,booking_id,status', 'careNotes.author:id,name',
        ])->whereIn('status', [Booking::STATUS_WAITING, Booking::STATUS_CONFIRMED, Booking::STATUS_PROGRESS])
          ->orderBy('booking_date')->orderBy('booking_time')->get();

        $columns = collect([Booking::STATUS_WAITING, Booking::STATUS_CONFIRMED, Booking::STATUS_PROGRESS])
            ->mapWithKeys(fn (string $status) => [$status => $bookings->where('status', $status)->values()]);
        $stats = [
            'waiting' => $bookings->where('status', Booking::STATUS_WAITING)->count(),
            'confirmed' => $bookings->where('status', Booking::STATUS_CONFIRMED)->count(),
            'progress' => $bookings->where('status', Booking::STATUS_PROGRESS)->count(),
        ];

        return view('owner.care-board.index', compact('business', 'columns', 'stats'));
    }
}
