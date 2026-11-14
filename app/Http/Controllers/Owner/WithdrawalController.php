<?php

namespace App\Http\Controllers\Owner;

use App\Models\Withdrawal;
use App\Services\PaymentFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalController extends OwnerController
{
    public function index(Request $request, PaymentFinancialService $finance): View
    {
        $business=$this->business($request);
        $withdrawals=Withdrawal::where('business_id',$business->id)->latest()->paginate(15);
        return view('owner.withdrawals.index',['business'=>$business,'withdrawals'=>$withdrawals,'balance'=>$finance->availableBalance($business->id)]);
    }

    public function store(Request $request, PaymentFinancialService $finance): RedirectResponse
    {
        $business=$this->business($request);
        $data=$request->validate([
            'amount'=>['required','numeric','gt:0'],
            'bank_name'=>['required','string','max:60'],
            'bank_account_number'=>['required','string','max:60'],
            'bank_account_name'=>['required','string','max:120'],
            'idempotency_key'=>['required','uuid'],
        ]);
        $finance->requestWithdrawal($business->id,$request->user()->id,$data);
        return back()->with('success','Permintaan withdrawal dikirim dan menunggu persetujuan admin.');
    }
}
