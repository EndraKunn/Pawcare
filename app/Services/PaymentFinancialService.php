<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentReconciliationRun;
use App\Models\PaymentRefund;
use App\Models\PaymentSettlement;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentFinancialService
{
    public function availableBalance(int $businessId): float
    {
        $credits = (float) LedgerEntry::where('business_id',$businessId)->where('account_code','business_payable')->sum('credit');
        $debits = (float) LedgerEntry::where('business_id',$businessId)->where('account_code','business_payable')->sum('debit');
        $reserved=(float)Withdrawal::where('business_id',$businessId)->whereIn('status',[Withdrawal::REQUESTED,Withdrawal::APPROVED])->sum('amount');
        return round($credits - $debits - $reserved, 2);
    }

    public function settle(Payment $payment): PaymentSettlement
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::with('booking.business')->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== Payment::PAID || ! $payment->booking?->business_id) {
                throw ValidationException::withMessages(['payment'=>'Pembayaran belum lunas atau bisnis tidak ditemukan.']);
            }
            $existing = PaymentSettlement::where('payment_id',$payment->id)->first();
            if ($existing) return $existing;

            $gross = round((float)$payment->amount,2);
            $feePercent = max(0,min(100,(float)config('services.pawcare.platform_fee_percent',10)));
            $fee = round($gross * $feePercent / 100,2);
            $net = round($gross - $fee,2);
            $settlement = PaymentSettlement::create([
                'payment_id'=>$payment->id,'business_id'=>$payment->booking->business_id,
                'reference'=>'SET-'.Str::upper(Str::random(18)),
                'gross_amount'=>$gross,'platform_fee'=>$fee,'net_amount'=>$net,
                'source'=>$payment->gateway_provider ?: 'manual','status'=>'settled','settled_at'=>now(),
            ]);
            $group=(string)Str::uuid();
            LedgerEntry::insert([
                ['entry_group_id'=>$group,'business_id'=>null,'account_code'=>'gateway_clearing','debit'=>$gross,'credit'=>0,'source_type'=>'payment_settlement','source_id'=>$settlement->id,'idempotency_key'=>'settlement:'.$payment->id.':clearing','description'=>'Dana masuk dari pembayaran','created_at'=>now()],
                ['entry_group_id'=>$group,'business_id'=>null,'account_code'=>'platform_revenue','debit'=>0,'credit'=>$fee,'source_type'=>'payment_settlement','source_id'=>$settlement->id,'idempotency_key'=>'settlement:'.$payment->id.':fee','description'=>'Biaya layanan platform','created_at'=>now()],
                ['entry_group_id'=>$group,'business_id'=>$payment->booking->business_id,'account_code'=>'business_payable','debit'=>0,'credit'=>$net,'source_type'=>'payment_settlement','source_id'=>$settlement->id,'idempotency_key'=>'settlement:'.$payment->id.':business','description'=>'Saldo bersih bisnis','created_at'=>now()],
            ]);
            return $settlement;
        });
    }

    public function requestWithdrawal(int $businessId, int $userId, array $data): Withdrawal
    {
        $amount=round((float)$data['amount'],2);
        if ($amount <= 0 || $amount > $this->availableBalance($businessId)) {
            throw ValidationException::withMessages(['amount'=>'Nominal penarikan melebihi saldo tersedia.']);
        }
        return DB::transaction(function () use ($businessId,$userId,$amount,$data) {
            \App\Models\Business::whereKey($businessId)->lockForUpdate()->firstOrFail();
            $key=(string)($data['idempotency_key'] ?? Str::uuid());
            $existing=Withdrawal::where('idempotency_key',$key)->first();
            if ($existing) return $existing;
            if ($amount > $this->availableBalance($businessId)) {
                throw ValidationException::withMessages(['amount'=>'Saldo tersedia berubah; periksa kembali nominal withdrawal.']);
            }
            return Withdrawal::create([
                'business_id'=>$businessId,'requested_by'=>$userId,'amount'=>$amount,
                'bank_name'=>$data['bank_name'],'bank_account_number'=>$data['bank_account_number'],
                'bank_account_name'=>$data['bank_account_name'],'status'=>Withdrawal::REQUESTED,
                'idempotency_key'=>$key,
            ]);
        });
    }

    public function approveWithdrawal(Withdrawal $withdrawal, int $adminId): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal,$adminId) {
            $w=Withdrawal::lockForUpdate()->findOrFail($withdrawal->id);
            if ($w->status !== Withdrawal::REQUESTED) throw ValidationException::withMessages(['withdrawal'=>'Status penarikan bukan requested.']);
            if ($w->amount > $this->availableBalance($w->business_id) + (float)$w->amount) throw ValidationException::withMessages(['withdrawal'=>'Saldo bisnis tidak mencukupi setelah memperhitungkan reservasi lain.']);
            $w->update(['status'=>Withdrawal::APPROVED,'approved_by'=>$adminId]);
            return $w;
        });
    }

    public function rejectWithdrawal(Withdrawal $withdrawal, int $adminId, string $reason): Withdrawal
    {
        if ($withdrawal->status !== Withdrawal::REQUESTED) throw ValidationException::withMessages(['withdrawal'=>'Penarikan tidak dapat ditolak pada status ini.']);
        $withdrawal->update(['status'=>Withdrawal::REJECTED,'approved_by'=>$adminId,'rejection_reason'=>$reason]);
        return $withdrawal->fresh();
    }

    public function markWithdrawalPaid(Withdrawal $withdrawal, int $adminId, string $reference): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal,$adminId,$reference) {
            $w=Withdrawal::lockForUpdate()->findOrFail($withdrawal->id);
            if ($w->status !== Withdrawal::APPROVED) throw ValidationException::withMessages(['withdrawal'=>'Penarikan harus disetujui sebelum dibayar.']);
            if ($w->amount > $this->availableBalance($w->business_id) + (float)$w->amount) throw ValidationException::withMessages(['withdrawal'=>'Saldo tidak mencukupi setelah memperhitungkan reservasi lain.']);
            $w->update(['status'=>Withdrawal::PAID,'processed_by'=>$adminId,'reference'=>$reference,'processed_at'=>now()]);
            $group=(string)Str::uuid();
            LedgerEntry::create([
                'entry_group_id'=>$group,'business_id'=>$w->business_id,'account_code'=>'business_payable',
                'debit'=>$w->amount,'credit'=>0,'source_type'=>'withdrawal','source_id'=>$w->id,
                'idempotency_key'=>'withdrawal:'.$w->id.':business','description'=>'Penarikan saldo bisnis','created_at'=>now(),
            ]);
            LedgerEntry::create([
                'entry_group_id'=>$group,'business_id'=>null,'account_code'=>'cash_clearing',
                'debit'=>0,'credit'=>$w->amount,'source_type'=>'withdrawal','source_id'=>$w->id,
                'idempotency_key'=>'withdrawal:'.$w->id.':cash','description'=>'Dana keluar untuk withdrawal','created_at'=>now(),
            ]);
            return $w;
        });
    }

    public function requestRefund(Payment $payment, int $adminId, float $amount, string $reason): PaymentRefund
    {
        if ($payment->status !== Payment::PAID) throw ValidationException::withMessages(['payment'=>'Refund hanya untuk pembayaran lunas.']);
        $already=(float)PaymentRefund::where('payment_id',$payment->id)->whereIn('status',['requested','pending','succeeded'])->sum('amount');
        if ($amount <= 0 || $amount + $already > (float)$payment->amount) throw ValidationException::withMessages(['amount'=>'Nominal refund melebihi sisa pembayaran.']);
        $refund=PaymentRefund::create([
            'payment_id'=>$payment->id,'requested_by'=>$adminId,'amount'=>$amount,'reason'=>$reason,
            'status'=>'requested','idempotency_key'=>(string)Str::uuid(),
        ]);
        if ($payment->gateway_provider === 'midtrans' && $payment->gateway_reference) {
            $key=(string)config('services.midtrans.server_key');
            $base=config('services.midtrans.is_production') ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
            $refundKey='PAW-REF-'.$refund->id.'-'.Str::upper(Str::random(5));
            $response=Http::withBasicAuth($key,'')->acceptJson()->asJson()->timeout(20)
                ->post($base.'/v2/'.$payment->gateway_reference.'/refund',[
                    'refund_key'=>$refundKey,
                    'amount'=>(int)round($amount),
                    'reason'=>$reason,
                ]);
            $refund->update(['status'=>$response->successful()?'pending':'failed','provider_reference'=>$refundKey,'response_payload'=>$response->json()]);
            if (!$response->successful()) throw ValidationException::withMessages(['refund'=>'Midtrans menolak permintaan refund. Periksa log provider.']);
        }
        return $refund->fresh();
    }

    public function reconcile(int $adminId): PaymentReconciliationRun
    {
        if (! filled(config('services.midtrans.server_key'))) {
            throw ValidationException::withMessages(['midtrans'=>'MIDTRANS_SERVER_KEY belum diatur.']);
        }
        $run=PaymentReconciliationRun::create(['provider'=>'midtrans','started_by'=>$adminId,'status'=>'running','started_at'=>now()]);
        $details=[]; $checked=0; $matched=0; $mismatch=0;
        $payments=Payment::where('gateway_provider','midtrans')->whereNotNull('gateway_reference')->whereIn('gateway_status',['pending','settlement'])->limit(200)->get();
        foreach($payments as $payment) {
            $checked++;
            $key=(string)config('services.midtrans.server_key');
            $base=config('services.midtrans.is_production')?'https://api.midtrans.com':'https://api.sandbox.midtrans.com';
            try {
                $response=Http::withBasicAuth($key,'')->acceptJson()->timeout(12)->get($base.'/v2/'.rawurlencode($payment->gateway_reference).'/status');
                $remote=$response->json('transaction_status');
                $normalized=match((string)$remote){'capture','settlement'=>'settlement','pending'=>'pending','expire'=>'expire','cancel'=>'cancel','deny'=>'deny',default=>(string)$remote};
                $same=$response->successful() && $normalized === (string)$payment->gateway_status;
                if($same) $matched++; else $mismatch++;
                $details[]=['payment_id'=>$payment->id,'order_id'=>$payment->gateway_reference,'local_status'=>$payment->gateway_status,'remote_status'=>$remote,'matched'=>$same,'http_status'=>$response->status()];
            } catch (\Throwable $e) {
                $mismatch++;
                $details[]=['payment_id'=>$payment->id,'order_id'=>$payment->gateway_reference,'error'=>'Provider status unavailable'];
            }
        }
        $run->update(['status'=>'completed','checked_count'=>$checked,'matched_count'=>$matched,'mismatch_count'=>$mismatch,'details'=>$details,'finished_at'=>now()]);
        return $run->fresh();
    }
    public function completeRefund(PaymentRefund $refund, array $providerPayload = []): PaymentRefund
    {
        return DB::transaction(function () use ($refund,$providerPayload) {
            $r=PaymentRefund::lockForUpdate()->findOrFail($refund->id);
            if ($r->status === 'succeeded') return $r;
            $payment=Payment::with('booking')->lockForUpdate()->findOrFail($r->payment_id);
            $providerAmount=$providerPayload['refund_amount'] ?? $providerPayload['amount'] ?? null;
            if ($providerAmount !== null && abs((float)$providerAmount - (float)$r->amount) > 0.01) {
                throw ValidationException::withMessages(['refund_amount'=>'Nominal refund dari provider tidak sesuai permintaan.']);
            }
            $settlement=PaymentSettlement::where('payment_id',$payment->id)->first();
            $r->update(['status'=>'succeeded','response_payload'=>$providerPayload,'processed_at'=>now()]);
            if ($settlement) {
                $ratio=min(1,(float)$r->amount/max((float)$settlement->gross_amount,0.01));
                $gross=round((float)$r->amount,2);
                $fee=round((float)$settlement->platform_fee*$ratio,2);
                $net=round($gross-$fee,2);
                $group=(string)Str::uuid();
                LedgerEntry::insert([
                    ['entry_group_id'=>$group,'business_id'=>$settlement->business_id,'account_code'=>'business_payable','debit'=>$net,'credit'=>0,'source_type'=>'refund','source_id'=>$r->id,'idempotency_key'=>'refund:'.$r->id.':business','description'=>'Pembalikan saldo bisnis karena refund','created_at'=>now()],
                    ['entry_group_id'=>$group,'business_id'=>null,'account_code'=>'platform_revenue','debit'=>$fee,'credit'=>0,'source_type'=>'refund','source_id'=>$r->id,'idempotency_key'=>'refund:'.$r->id.':fee','description'=>'Pembalikan biaya platform karena refund','created_at'=>now()],
                    ['entry_group_id'=>$group,'business_id'=>null,'account_code'=>'gateway_clearing','debit'=>0,'credit'=>$gross,'source_type'=>'refund','source_id'=>$r->id,'idempotency_key'=>'refund:'.$r->id.':clearing','description'=>'Dana keluar untuk refund','created_at'=>now()],
                ]);
            }
            return $r;
        });
    }

    public function completeManualRefund(PaymentRefund $refund, int $adminId, string $reference): PaymentRefund
    {
        if ($refund->status !== 'requested') throw ValidationException::withMessages(['refund'=>'Refund bukan berstatus requested.']);
        $refund->update(['provider_reference'=>$reference]);
        $this->completeRefund($refund->fresh(), ['processed_by'=>$adminId,'reference'=>$reference,'channel'=>'manual']);
        return $refund->fresh();
    }

}
