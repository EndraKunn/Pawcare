@extends('layouts.dashboard')
@section('title', 'Booking Saya')
@section('heading', 'Booking saya')
@section('subtitle', 'Pantau perkembangan setiap layanan.')
@section('actions')
  <a class="btn line" href="{{ route('customer.bookings.cart') }}">🛒 Keranjang booking</a>
  <a class="btn" href="{{ route('catalog') }}">+ Booking baru</a>
@endsection

@section('content')
<div class="tabs">
  @foreach (['semua' => 'Semua', 'aktif' => 'Aktif', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $k => $label)
    <a href="{{ route('customer.bookings.index', ['filter' => $k]) }}" style="text-decoration:none"><button type="button" class="{{ $filter === $k ? 'on' : '' }}">{{ $label }} ({{ $counts[$k] }})</button></a>
  @endforeach
</div>
@if ($bookings->isEmpty())
  <div class="card"><x-empty icon="📅" title="Belum ada booking" /></div>
@else
  <div class="tw"><table>
    <thead><tr><th>Invoice</th><th>Bisnis</th><th>Hewan</th><th>Jadwal</th><th class="num">Total</th><th>Status</th><th>Pembayaran</th><th></th></tr></thead>
    <tbody>
      @foreach ($bookings as $b)
        <tr>
          <td class="nw"><b>{{ $b->invoice_number }}</b></td><td>{{ $b->business->business_name }}</td><td>{{ $b->pet->name }}</td>
          <td class="nw">{{ $b->schedule_label }}</td><td class="num nw">{{ rupiah($b->total_price) }}</td>
          <td><x-status :status="$b->status" /></td><td><x-pay :status="$b->payment->status" /></td>
          <td class="acts">
            @if ($b->status !== 'Dibatalkan' && in_array($b->payment->status, ['Belum Dibayar', 'Ditolak']))<a class="btn sm" href="{{ route('customer.bookings.pay', $b->id) }}">Bayar</a>@endif
            <a class="btn sm line" href="{{ route('customer.bookings.show', $b->id) }}">Detail</a>
          </td>
        </tr>
      @endforeach
    </tbody></table></div>
  {{ $bookings->links() }}
@endif
@endsection
