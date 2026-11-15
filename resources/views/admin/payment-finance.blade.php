@extends('layouts.dashboard')
@section('title','Keuangan Platform')
@section('heading','Settlement, ledger & withdrawal')
@section('subtitle','Tinjau saldo bisnis, permintaan withdrawal, refund, dan rekonsiliasi.')
@section('actions')
<form method="POST" action="{{ route('admin.finance.reconcile') }}">@csrf<button class="btn line">Jalankan rekonsiliasi Midtrans</button></form>
@endsection
@section('content')
<div class="grid g3">
 <x-stat label="Platform fee tercatat" :value="rupiah($platformFees)" />
 <x-stat label="Settlement terbaru" :value="$settlements->count()" />
 <x-stat label="Permintaan withdrawal" :value="$withdrawals->total()" />
</div>
<div class="card mt2"><h3>Withdrawal</h3><div class="tw"><table><thead><tr><th>Bisnis</th><th>Pemohon</th><th>Nominal</th><th>Bank</th><th>Status / Aksi</th></tr></thead><tbody>
@forelse($withdrawals as $w)<tr><td>{{ $w->business->business_name }}</td><td>{{ $w->requester->name }}</td><td>{{ rupiah($w->amount) }}</td><td>{{ $w->bank_name }} · {{ $w->bank_account_number }}</td><td>{{ $w->status }}
@if($w->status==='requested')
<form method="POST" action="{{ route('admin.finance.withdrawals.approve',$w) }}">@csrf @method('PATCH')<button class="btn">Setujui</button></form>
<form method="POST" action="{{ route('admin.finance.withdrawals.reject',$w) }}">@csrf @method('PATCH')<input name="reason" placeholder="Alasan penolakan" required><button class="btn line">Tolak</button></form>
@elseif($w->status==='approved')
<form method="POST" action="{{ route('admin.finance.withdrawals.paid',$w) }}">@csrf @method('PATCH')<input name="reference" placeholder="Referensi transfer" required><button class="btn">Tandai dibayar</button></form>
@endif
</td></tr>@empty<tr><td colspan="5">Belum ada permintaan withdrawal.</td></tr>@endforelse
</tbody></table></div>{{ $withdrawals->links() }}</div>
<div class="card mt2"><h3>Settlement terbaru</h3><div class="tw"><table><thead><tr><th>Referensi</th><th>Bisnis</th><th>Gross</th><th>Fee</th><th>Net</th><th>Tanggal</th></tr></thead><tbody>
@foreach($settlements as $s)<tr><td>{{ $s->reference }}</td><td>{{ $s->business->business_name }}</td><td>{{ rupiah($s->gross_amount) }}</td><td>{{ rupiah($s->platform_fee) }}</td><td>{{ rupiah($s->net_amount) }}</td><td>{{ $s->settled_at->format('d/m/Y H:i') }}</td></tr>@endforeach
</tbody></table></div></div>
<div class="card mt2"><h3>Ajukan refund pembayaran</h3><div class="tw"><table><thead><tr><th>Payment</th><th>Bisnis</th><th>Nominal</th><th>Form refund</th></tr></thead><tbody>
@foreach($paidPayments as $payment)<tr><td>#{{ $payment->id }}</td><td>{{ $payment->booking?->business?->business_name }}</td><td>{{ rupiah($payment->amount) }}</td><td><form method="POST" action="{{ route('admin.finance.refunds.store',$payment) }}">@csrf<input type="number" name="amount" min="1" max="{{ (int)$payment->amount }}" placeholder="Nominal" required><input name="reason" placeholder="Alasan refund" required><button class="btn line">Ajukan</button></form></td></tr>@endforeach
</tbody></table></div></div>
<div class="card mt2"><h3>Refund terbaru</h3><div class="tw"><table><thead><tr><th>Payment</th><th>Nominal</th><th>Alasan</th><th>Status</th></tr></thead><tbody>
@forelse($refunds as $r)<tr><td>#{{ $r->payment_id }}</td><td>{{ rupiah($r->amount) }}</td><td>{{ $r->reason }}</td><td>{{ $r->status }}
@if($r->status==='requested' && $r->payment?->gateway_provider !== 'midtrans')
<form method="POST" action="{{ route('admin.finance.refunds.complete',$r) }}">@csrf @method('PATCH')<input name="reference" placeholder="Referensi refund" required><button class="btn line">Selesaikan manual</button></form>
@endif
</td></tr>@empty<tr><td colspan="4">Belum ada refund.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
