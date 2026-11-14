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
    public function index(): View
    {
        return view('admin.payment-finance',[
            'withdrawals'=>Withdrawal::with('business','requester')->latest()->paginate(15),
            'refunds'=>PaymentRefund::with('payment.booking')->latest()->limit(20)->get(),
            'settlements'=>PaymentSettlement::with('business','payment')->latest()->limit(20)->get(),
            'platformFees'=>PaymentSettlement::sum('platform_fee'),
            'paidPayments'=>Payment::with('booking.business')->where('status',Payment::PAID)->latest('paid_at')->limit(20)->get(),
        ]);
    }

    public function approve(Request $request, int $withdrawal, PaymentFinancialService $finance): RedirectResponse
    {
        $item=Withdrawal::findOrFail($withdrawal);
        $finance->approveWithdrawal($item,(int)$request->user()->id);
        return back()->with('success','Withdrawal disetujui. Lanjutkan pembayaran transfer dan catat referensinya.');
    }

    public function reject(Request $request, int $withdrawal, PaymentFinancialService $finance): RedirectResponse
    {
        $data=$request->validate(['reason'=>['required','string','min:5','max:500']]);
        $finance->rejectWithdrawal(Withdrawal::findOrFail($withdrawal),(int)$request->user()->id,$data['reason']);
        return back()->with('success','Withdrawal ditolak.');
    }

    public function paid(Request $request, int $withdrawal, PaymentFinancialService $finance): RedirectResponse
    {
        $data=$request->validate(['reference'=>['required','string','max:100']]);
        $finance->markWithdrawalPaid(Withdrawal::findOrFail($withdrawal),(int)$request->user()->id,$data['reference']);
        return back()->with('success','Withdrawal ditandai telah dibayar dan ledger diperbarui.');
    }

    public function refund(Request $request, int $payment, PaymentFinancialService $finance): RedirectResponse
    {
        $data=$request->validate(['amount'=>['required','numeric','gt:0'],'reason'=>['required','string','min:5','max:500']]);
        $finance->requestRefund(Payment::findOrFail($payment),(int)$request->user()->id,(float)$data['amount'],$data['reason']);
        return back()->with('success','Permintaan refund dicatat dan dikirim ke gateway bila pembayaran berasal dari Midtrans.');
    }


    public function completeManualRefund(Request $request, int $refund, PaymentFinancialService $finance): RedirectResponse
    {
        $data=$request->validate(['reference'=>['required','string','max:100']]);
        $item=PaymentRefund::findOrFail($refund);
        abort_if($item->payment?->gateway_provider === 'midtrans', 422, 'Refund Midtrans harus dikonfirmasi melalui webhook provider.');
        $finance->completeManualRefund($item,(int)$request->user()->id,$data['reference']);
        return back()->with('success','Refund manual diselesaikan dan ledger dibalik.');
    }

    public function reconcile(Request $request, PaymentFinancialService $finance): RedirectResponse
    {
        $run=$finance->reconcile((int)$request->user()->id);
        return back()->with('success',"Rekonsiliasi selesai: {$run->matched_count} cocok, {$run->mismatch_count} perlu diperiksa.");
    }
}
