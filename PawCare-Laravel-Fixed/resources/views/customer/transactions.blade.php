@extends('layouts.dashboard')
@section('title', 'Riwayat Transaksi')
@section('heading', 'Riwayat transaksi')
@section('subtitle', 'Semua pembayaran yang sudah terverifikasi.')

@section('content')
<div class="grid g3" style="margin-bottom:18px">
  <x-stat label="Total dibayar" :value="rupiah($total)" :note="$count.' transaksi'" />
  <x-stat label="Transaksi bulan ini" :value="rupiah($thisMonth)" />
  <x-stat label="Bisnis dikunjungi" :value="$businessCount" />
</div>
@if ($payments->isEmpty())
  <div class="card"><x-empty icon="🧾" title="Belum ada transaksi terverifikasi" /></div>
@else
  <div class="tw"><table><thead><tr><th>Kode transaksi</th><th>Invoice</th><th>Bisnis</th><th>Tanggal bayar</th><th class="num">Jumlah</th></tr></thead><tbody>
    @foreach ($payments as $p)
      <tr><td class="nw">{{ $p->transaction_code }}</td><td><a class="gold-t" href="{{ route('customer.bookings.show', $p->booking_id) }}">{{ $p->booking->invoice_number }}</a></td><td>{{ $p->booking->business->business_name }}</td><td class="nw">{{ $p->paid_at->translatedFormat('d M Y, H.i') }}</td><td class="num nw">{{ rupiah($p->amount) }}</td></tr>
    @endforeach
  </tbody></table></div>
  {{ $payments->links() }}
@endif
@endsection
