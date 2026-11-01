@extends('layouts.dashboard')
@section('title', 'Pembayaran')
@section('heading', 'Pembayaran masuk')
@section('subtitle', 'Periksa bukti transfer sebelum menandai Dibayar.')

@section('content')
<div class="tabs">
  <a href="{{ route('owner.payments.index', ['filter' => 'perlu']) }}" style="text-decoration:none"><button type="button" class="{{ $filter === 'perlu' ? 'on' : '' }}">Perlu verifikasi ({{ $pendingCount }})</button></a>
  <a href="{{ route('owner.payments.index', ['filter' => 'semua']) }}" style="text-decoration:none"><button type="button" class="{{ $filter !== 'perlu' ? 'on' : '' }}">Semua</button></a>
</div>
@if ($payments->isEmpty())
  <div class="card"><x-empty icon="💳" title="Tidak ada pembayaran" /></div>
@else
  <div class="tw"><table><thead><tr><th>Invoice</th><th>Pelanggan</th><th class="num">Jumlah</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach ($payments as $p)
      <tr><td><b>{{ $p->booking->invoice_number }}</b></td><td>{{ $p->booking->customer->name }}</td><td class="num nw">{{ rupiah($p->amount) }}</td><td><x-pay :status="$p->status" /></td><td><a class="btn sm {{ $p->status === 'Menunggu Verifikasi' ? '' : 'line' }}" href="{{ route('owner.payments.show', $p->id) }}">{{ $p->status === 'Menunggu Verifikasi' ? 'Periksa' : 'Detail' }}</a></td></tr>
    @endforeach
  </tbody></table></div>
  {{ $payments->links() }}
@endif
@endsection
