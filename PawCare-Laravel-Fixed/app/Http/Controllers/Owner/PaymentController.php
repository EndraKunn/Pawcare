<?php

namespace App\Http\Controllers\Owner;

use App\Models\Payment;
use App\Notifications\PawCareStatusNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends OwnerController
{
    /**
     * Menampilkan daftar pembayaran milik bisnis Owner.
     */
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'perlu');

        $payments = $this->payments($request)
            ->with('booking.customer')
            ->when(
                $filter === 'perlu',
                fn ($q) => $q->where('status', Payment::PENDING)
            )
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $pendingCount = $this->payments($request)
            ->where('status', Payment::PENDING)
            ->count();

        return view(
            'owner.payments.index',
            compact('payments', 'filter', 'pendingCount')
        );
    }

    /**
     * Menampilkan detail pembayaran.
     */
    public function show(Request $request, int $payment): View
    {
        $model = $this->payments($request)
            ->with([
                'booking.customer',
                'booking.business',
                'booking.items.service',
                'verifier',
            ])
            ->findOrFail($payment);

        return view('owner.payments.show', [
            'payment' => $model,
        ]);
    }

    /**
     * Memverifikasi bukti pembayaran dan mengirim notifikasi kepada Customer.
     */
    public function verify(
        Request $request,
        int $payment
    ): RedirectResponse {
        $model = $this->payments($request)
            ->with('booking.customer')
            ->findOrFail($payment); // pembayaran bisnis lain -> 404

        if (
            $model->status !== Payment::PENDING
            || ! $model->proof_path
        ) {
            return back()->with(
                'error',
                'Hanya pembayaran berstatus Menunggu Verifikasi yang dapat diverifikasi.'
            );
        }

        DB::transaction(function () use ($request, $model) {
            $model->update([
                'status' => Payment::PAID,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'paid_at' => now(),
                'reject_reason' => null,
            ]);

            $booking = $model->booking;
            $customer = $booking?->customer;

            if ($booking && $customer) {
                $customer->notify(
                    new PawCareStatusNotification(
                        title: 'Pembayaran diverifikasi',
                        message: "Pembayaran untuk booking {$booking->invoice_number} telah diverifikasi dan berstatus ".Payment::PAID.'.',
                        url: route('customer.bookings.show', $booking->id),
                    )
                );
            }
        });

        return redirect()
            ->route('owner.payments.index')
            ->with(
                'success',
                'Pembayaran diverifikasi dan ditandai Dibayar.'
            );
    }

    /**
     * Menolak bukti pembayaran dan memberi notifikasi kepada Customer.
     */
    public function reject(
        Request $request,
        int $payment
    ): RedirectResponse {
        $model = $this->payments($request)
            ->with('booking.customer')
            ->findOrFail($payment);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], [
            'reason' => 'alasan penolakan',
        ]);

        if ($model->status !== Payment::PENDING) {
            return back()->with(
                'error',
                'Hanya pembayaran berstatus Menunggu Verifikasi yang dapat ditolak.'
            );
        }

        $reason = trim($data['reason']);

        DB::transaction(function () use ($request, $model, $reason) {
            $model->update([
                'status' => Payment::REJECTED,
                'reject_reason' => $reason,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'paid_at' => null,
            ]);

            $booking = $model->booking;
            $customer = $booking?->customer;

            if ($booking && $customer) {
                $customer->notify(
                    new PawCareStatusNotification(
                        title: 'Pembayaran ditolak',
                        message: "Pembayaran untuk booking {$booking->invoice_number} ditolak. Alasan: {$model->reject_reason}",
                        url: route('customer.bookings.pay', $booking->id),
                    )
                );
            }
        });

        return redirect()
            ->route('owner.payments.index')
            ->with(
                'success',
                'Bukti pembayaran ditolak. Pelanggan dapat mengunggah ulang.'
            );
    }
}
