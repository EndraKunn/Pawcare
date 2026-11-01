@extends('layouts.dashboard')
@section('title', 'Pantau Booking')
@section('heading', 'Pantau booking')
@section('subtitle', 'Seluruh booking dari semua bisnis (hanya baca).')

@section('content')
<form method="GET" class="row wrapf" style="margin-bottom:14px;align-items:flex-end">
  <label class="fld" style="width:260px"><input name="q" value="{{ $q }}" placeholder="🔎 Nomor invoice"></label>
  <label class="fld"><select name="status"><option value="">Semua status</option>@foreach (\App\Models\Booking::STATUSES as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></label>
  <button class="btn sm line" type="submit" style="height:44px">Filter</button>
</form>
@if ($bookings->isEmpty())<div class="card"><x-empty icon="📅" title="Tidak ada booking" /></div>@else
<div class="tw"><table><thead><tr><th>Invoice</th><th>Bisnis</th><th>Pelanggan</th><th>Hewan</th><th>Jadwal</th><th class="num">Total</th><th>Status</th><th>Pembayaran</th></tr></thead><tbody>
  @foreach ($bookings as $b)<tr><td class="nw"><b>{{ $b->invoice_number }}</b></td><td>{{ $b->business->business_name }}</td><td>{{ $b->customer->name }}</td><td>{{ $b->pet->name }}</td><td class="nw">{{ $b->schedule_label }}</td><td class="num nw">{{ rupiah($b->total_price) }}</td><td><x-status :status="$b->status" /></td><td><x-pay :status="$b->payment->status" /></td></tr>@endforeach
</tbody></table></div>{{ $bookings->links() }}@endif
@endsection
