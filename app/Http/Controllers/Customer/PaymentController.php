<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function show(Request $request, int $booking): View
    {
        $model = $this->findBooking($request, $booking);

        return view('customer.bookings.pay', [
            'booking' => $model,
            'gatewayChannels' => PaymentGatewayService::CHANNELS,
        ]);
    }

    public function store(Request $request, int $booking): RedirectResponse
    {
        $model = $this->findBooking($request, $booking);
        $payment = $model->payment;

        if ($model->status === Booking::STATUS_CANCELLED) {
            return back()->with('error', 'Booking yang dibatalkan tidak dapat dibayar.');
        }
        if (! in_array($payment->status, [Payment::UNPAID, Payment::REJECTED], true)) {
            return back()->with('error', 'Bukti pembayaran sudah dikirim atau pembayaran sudah diverifikasi.');
        }

        $request->validate([
            'proof' => Payment::proofRules(2048),
        ], [], ['proof' => 'bukti pembayaran']);

        // Disimpan di disk privat (storage/app/private/payment-proofs), nama file diacak oleh Laravel.
        $path = $request->file('proof')->store(Payment::PROOF_DIRECTORY, Payment::PROOF_DISK);

        if ($payment->proof_path) {
            Storage::disk(Payment::PROOF_DISK)->delete($payment->proof_path);
        }

        $payment->update([
            'payment_method' => Payment::METHOD_MANUAL,
            'proof_path' => $path,
            'status' => Payment::PENDING,
            'reject_reason' => null,
            'verified_by' => null,
            'verified_at' => null,
            'paid_at' => null,
        ]);

        return redirect()->route('customer.bookings.show', $model->id)
            ->with('success', 'Bukti pembayaran terkirim. Menunggu verifikasi bisnis.');
    }

    /**
     * Membuat sesi pembayaran melalui Payment Gateway (Midtrans Sandbox)
     * menggunakan nominal dari database server.
     */
    public function checkoutGateway(
        Request $request,
        int $booking,
        PaymentGatewayService $gateway
    ): RedirectResponse {
        $model = $this->findBooking($request, $booking);

        $data = $request->validate([
            'channel' => ['required', 'string'],
        ]);

        $payment = $gateway->createTransaction($model, $request->user(), $data['channel']);

        return redirect()->route('customer.bookings.pay', $model->id)
            ->with('success', "Transaksi Payment Gateway ({$payment->gateway_reference}) berhasil dibuat. Silakan selesaikan pembayaran sesuai instruksi.");
    }

    private function findBooking(Request $request, int $id): Booking
    {
        return Booking::forCustomer($request->user())
            ->with(['business', 'pet', 'items.service', 'payment'])
            ->findOrFail($id);
    }
}