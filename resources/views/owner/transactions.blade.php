@extends('layouts.dashboard')
@section('title', 'Transaksi')
@section('heading', 'Data transaksi')
@section('subtitle', 'Transaksi tercatat setelah pembayaran diverifikasi.')

@section('content')
<div class="grid g3" style="margin-bottom:18px">
  <x-stat label="Total transaksi" :value="rupiah($total)" />
  <x-stat label="Jumlah transaksi" :value="$count" />
  <x-stat label="Rata-rata per transaksi" :value="rupiah($avg)" />
</div>
@if ($payments->isEmpty())
  <div class="card"><x-empty icon="🧾" title="Belum ada transaksi" /></div>
@else
  <div class="tw"><table><thead><tr><th>Kode transaksi</th><th>Invoice</th><th>Pelanggan</th><th>Dibayar pada</th><th class="num">Jumlah</th></tr></thead><tbody>
    @foreach ($payments as $p)
      <tr><td class="nw">{{ $p->transaction_code }}</td><td>{{ $p->booking->invoice_number }}</td><td>{{ $p->booking->customer->name }}</td><td class="nw">{{ $p->paid_at->translatedFormat('d M Y, H.i') }}</td><td class="num nw">{{ rupiah($p->amount) }}</td></tr>
    @endforeach
  </tbody></table></div>
  {{ $payments->links() }}
@endif
@endsection
