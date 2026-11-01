<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Business;
use App\Services\BookingService;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings)
    {
    }

    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'semua');
        $base = fn () => Booking::forCustomer($request->user());

        $query = $base()->with(['business', 'pet', 'payment', 'review'])->latest('id');
        match ($filter) {
            'aktif' => $query->whereIn('status', [Booking::STATUS_WAITING, Booking::STATUS_CONFIRMED, Booking::STATUS_PROGRESS]),
            'selesai' => $query->where('status', Booking::STATUS_DONE),
            'dibatalkan' => $query->where('status', Booking::STATUS_CANCELLED),
            default => null,
        };

        $counts = [
            'semua' => $base()->count(),
            'aktif' => $base()->whereIn('status', [Booking::STATUS_WAITING, Booking::STATUS_CONFIRMED, Booking::STATUS_PROGRESS])->count(),
            'selesai' => $base()->where('status', Booking::STATUS_DONE)->count(),
            'dibatalkan' => $base()->where('status', Booking::STATUS_CANCELLED)->count(),
        ];

        return view('customer.bookings.index', [
            'bookings' => $query->paginate(10)->withQueryString(),
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    public function create(Request $request, SlotAvailabilityService $slotService): View|RedirectResponse
    {
        $business = Business::active()->findOrFail((int) $request->query('business'));
        $pets = $request->user()->pets()->whereNull('archived_at')->orderBy('name')->get();

        // FR-13: pelanggan harus memiliki minimal satu data hewan aktif.
        if ($pets->isEmpty()) {
            return redirect()->route('customer.pets.create')
                ->with('error', 'Tambahkan minimal satu data hewan sebelum membuat booking.');
        }

        $services = $business->services()->active()->orderBy('category')->orderBy('name')->get();

        $dateParam = (string) $request->query('date', now()->toDateString());
        try {
            $selectedDate = Carbon::parse($dateParam)->toDateString();
        } catch (\Throwable) {
            $selectedDate = now()->toDateString();
        }
        if ($selectedDate < now()->toDateString()) {
            $selectedDate = now()->toDateString();
        }

        $slotInfo = $slotService->getDailySlots($business, $selectedDate);

        return view('customer.bookings.create', [
            'business' => $business,
            'pets' => $pets,
            'services' => $services,
            'preselect' => (int) $request->query('service', 0),
            'selectedDate' => $selectedDate,
            'slotInfo' => $slotInfo,
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $booking = $this->bookings->create($request->user(), $request->bookingData());

        return redirect()->route('customer.bookings.pay', $booking->id)
            ->with('success', "Booking {$booking->invoice_number} berhasil dibuat. Silakan lakukan pembayaran.");
    }

    public function show(Request $request, int $booking): View
    {
        $model = Booking::forCustomer($request->user())
            ->with([
                'business.owner',
                'pet',
                'items.service',
                'payment',
                'statusLogs.changer',
                'review.reply.owner',
                'careNotes' => fn ($q) => $q->where('visible_to_customer', true)->with('author:id,name'),
                'conversation',
            ])
            ->findOrFail($booking);

        return view('customer.bookings.show', ['booking' => $model]);
    }

    public function cancel(Request $request, int $booking): RedirectResponse
    {
        $model = Booking::forCustomer($request->user())->findOrFail($booking);

        if ($model->status !== Booking::STATUS_WAITING) {
            return back()->with('error', 'Booking hanya dapat dibatalkan saat berstatus Menunggu.');
        }

        $this->bookings->changeStatus($model, $request->user(), Booking::STATUS_CANCELLED, 'Dibatalkan oleh pelanggan');

        return back()->with('success', 'Booking dibatalkan.');
    }
}
