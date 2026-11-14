@extends('layouts.dashboard')
@section('title', 'Detail Order '.$order->order_number)
@section('heading', 'Order berhasil dibuat')
@section('subtitle', 'Satu order dapat berisi beberapa booking layanan.')
@section('actions')<a class="btn line" href="{{ route('customer.bookings.index') }}">Semua booking</a>@endsection

@section('content')
<div class="card gold mb">
  <div class="row sp wrapf">
    <div>
      <small class="mut">Nomor order</small>
      <h2 style="margin:4px 0">{{ $order->order_number }}</h2>
      <p class="sub" style="margin:0">{{ $order->checked_out_at?->translatedFormat('d M Y, H:i') }}</p>
    </div>
    <x-status :status="$order->status" />
  </div>
  <div class="total mt"><span>Total order</span><b>{{ rupiah($order->total_amount) }}</b></div>
  <p class="note">Order induk dan seluruh booking turunannya disimpan dalam satu transaksi. Jika salah satu jadwal gagal divalidasi, seluruh checkout dibatalkan.</p>
</div>

@foreach ($order->bookings as $booking)
  <div class="card mb">
    <div class="row sp wrapf">
      <div>
        <h3 style="margin-bottom:4px">{{ $booking->business->business_name }}</h3>
        <p class="sub" style="margin:0">{{ $booking->pet->name }} · {{ $booking->schedule_label }}</p>
        <small class="mut">Invoice {{ $booking->invoice_number }}</small>
      </div>
      <x-status :status="$booking->status" />
    </div>
    <div class="tw mt">
      <table>
        <thead><tr><th>Layanan</th><th>Jumlah</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
          @foreach ($booking->items as $item)
            <tr>
              <td>{{ $item->service->name }}</td>
              <td>{{ $item->quantity }} {{ $item->service->unit }}</td>
              <td class="num nw">{{ rupiah($item->subtotal) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="row sp wrapf mt">
      <div><small class="mut">Pembayaran</small><div><x-pay :status="$booking->payment->status" /></div></div>
      <div class="row wrapf">
        @if ($booking->status !== 'Dibatalkan' && in_array($booking->payment->status, ['Belum Dibayar', 'Ditolak'], true))
          <a class="btn sm" href="{{ route('customer.bookings.pay', $booking->id) }}">Bayar booking</a>
        @endif
        <a class="btn sm line" href="{{ route('customer.bookings.show', $booking->id) }}">Detail booking</a>
      </div>
    </div>
  </div>
@endforeach
@endsection
