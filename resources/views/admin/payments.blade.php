@extends('layouts.dashboard')
@section('title', 'Pantau Pembayaran')
@section('heading', 'Pantau pembayaran')
@section('subtitle', 'Seluruh pembayaran dari semua bisnis (hanya baca).')

@section('content')
<form method="GET" class="row" style="margin-bottom:14px;align-items:flex-end"><label class="fld"><select name="status"><option value="">Semua status</option>@foreach (\App\Models\Payment::STATUSES as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></label><button class="btn sm line" type="submit" style="height:44px">Filter</button></form>
@if ($payments->isEmpty())<div class="card"><x-empty icon="💳" title="Tidak ada pembayaran" /></div>@else
<div class="tw"><table><thead><tr><th>Invoice</th><th>Bisnis</th><th>Pelanggan</th><th class="num">Jumlah</th><th>Status</th><th>Bukti</th></tr></thead><tbody>
  @foreach ($payments as $p)<tr><td class="nw"><b>{{ $p->booking->invoice_number }}</b></td><td>{{ $p->booking->business->business_name }}</td><td>{{ $p->booking->customer->name }}</td><td class="num nw">{{ rupiah($p->amount) }}</td><td><x-pay :status="$p->status" /></td><td>@if($p->proof_path)<a class="btn sm line" href="{{ route('payments.proof', $p->id) }}" target="_blank" rel="noopener">Lihat</a>@else<span class="mut">-</span>@endif</td></tr>@endforeach
</tbody></table></div>{{ $payments->links() }}@endif
@endsection
