<?php

namespace App\Http\Controllers\Owner;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends OwnerController
{
    public function __construct(private BookingService $bookingService)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $bookings = $this->bookings($request)
            ->with(['customer', 'pet', 'items.service', 'payment'])
            ->when(in_array($status, Booking::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('owner.bookings.index', compact('bookings', 'status'));
    }

    public function show(Request $request, int $booking): View
    {
        $model = $this->bookings($request)
            ->with([
                'customer',
                'pet.healthRecords',
                'items.service',
                'payment',
                'statusLogs.changer',
                'careNotes.author',
                'review.reply.owner',
                'conversation',
            ])
            ->findOrFail($booking); // booking bisnis lain -> 404

        return view('owner.bookings.show', ['booking' => $model]);
    }

    public function updateStatus(Request $request, int $booking): RedirectResponse
    {
        $model = $this->bookings($request)->findOrFail($booking);

        $data = $request->validate([
            'status' => ['required', Rule::in(Booking::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookingService->changeStatus($model, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('success', "Status booking diubah menjadi {$data['status']}.");
    }
}
