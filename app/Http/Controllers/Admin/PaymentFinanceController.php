<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentSettlement;
use App\Models\Withdrawal;
use App\Services\PaymentFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentFinanceController extends Controller
{
    /**
     * Halaman utama keuangan platform.
     */
    public function index(): View
    {
        return view('admin.payment-finance', [
            'withdrawals' => Withdrawal::with('business', 'requester')
                ->latest()
                ->paginate(15),

            'refunds' => PaymentRefund::with('payment.booking')
                ->latest()
                ->limit(20)
                ->get(),

            'settlements' => PaymentSettlement::with('business', 'payment')
                ->latest()
                ->limit(20)
                ->get(),

            'platformFees' => PaymentSettlement::sum('platform_fee'),

            'paidPayments' => Payment::with('booking.business')
                ->where('status', Payment::PAID)
                ->latest('paid_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Menyetujui permintaan withdrawal.
     */
    public function approve(
        Request $request,
        int $withdrawal,
        PaymentFinancialService $finance
    ): RedirectResponse {
        $item = Withdrawal::findOrFail($withdrawal);

        $finance->approveWithdrawal(
            $item,
            (int) $request->user()->id
        );

        return back()->with(
            'success',
            'Withdrawal disetujui. Lanjutkan pembayaran transfer dan catat referensinya.'
        );
    }

    /**
     * Menolak permintaan withdrawal.
     */
    public function reject(
        Request $request,
        int $withdrawal,
        PaymentFinancialService $finance
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ]);

        $item = Withdrawal::findOrFail($withdrawal);

        $finance->rejectWithdrawal(
            $item,
            (int) $request->user()->id,
            $data['reason']
        );

        return back()->with(
            'success',
            'Withdrawal berhasil ditolak.'
        );
    }

    /**
     * Menandai withdrawal telah dibayar.
     */
    public function paid(
        Request $request,
        int $withdrawal,
        PaymentFinancialService $finance
    ): RedirectResponse {
        $data = $request->validate([
            'reference' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $item = Withdrawal::findOrFail($withdrawal);

        $finance->markWithdrawalPaid(
            $item,
            (int) $request->user()->id,
            $data['reference']
        );

        return back()->with(
            'success',
            'Withdrawal ditandai telah dibayar dan ledger diperbarui.'
        );
    }

    /**
     * Mengajukan refund pembayaran.
     */
    public function refund(
        Request $request,
        int $payment,
        PaymentFinancialService $finance
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => [
                'required',
                'integer',
                'min:1',
            ],

            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],

            'idempotency_key' => [
                'required',
                'uuid',
            ],
        ]);

        $item = Payment::findOrFail($payment);

        $finance->requestRefund(
            $item,
            (int) $request->user()->id,
            (int) $data['amount'],
            $data['reason'],
            $data['idempotency_key']
        );

        return back()->with(
            'success',
            'Permintaan refund berhasil dicatat. Status selanjutnya mengikuti proses pembayaran dan konfirmasi provider.'
        );
    }

    /**
     * Menyelesaikan refund manual.
     */
    public function completeManualRefund(
        Request $request,
        int $refund,
        PaymentFinancialService $finance
    ): RedirectResponse {
        $data = $request->validate([
            'reference' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $item = PaymentRefund::with('payment')
            ->findOrFail($refund);

        abort_if(
            $item->payment?->gateway_provider === 'midtrans',
            422,
            'Refund Midtrans harus dikonfirmasi melalui webhook provider.'
        );

        $finance->completeManualRefund(
            $item,
            (int) $request->user()->id,
            $data['reference']
        );

        return back()->with(
            'success',
            'Refund manual berhasil diselesaikan dan ledger diperbarui.'
        );
    }

    /**
     * Menjalankan rekonsiliasi transaksi Midtrans.
     */
    public function reconcile(
        Request $request,
        PaymentFinancialService $finance
    ): RedirectResponse {
        $run = $finance->reconcile(
            (int) $request->user()->id
        );

        return back()->with(
            'success',
            "Rekonsiliasi selesai: {$run->matched_count} cocok, {$run->mismatch_count} perlu diperiksa."
        );
    }
}
