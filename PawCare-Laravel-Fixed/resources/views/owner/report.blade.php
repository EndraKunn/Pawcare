@extends('layouts.dashboard')
@section('title', 'Laporan Bisnis')
@section('heading', 'Laporan aktivitas bisnis')
@section('subtitle', 'Booking, layanan, pembayaran, dan transaksi milik bisnis Anda saja.')
@section('actions')
  <a class="btn line" href="{{ route('owner.reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">⬇ Ekspor CSV</a>
@endsection

@section('content')
<form method="GET" action="{{ route('owner.reports') }}" class="row wrapf" style="margin-bottom:18px;align-items:flex-end">
  <x-field name="from" label="Dari" type="date" :value="$from->toDateString()" />
  <x-field name="to" label="Sampai" type="date" :value="$to->toDateString()" />
  <button class="btn" type="submit" style="height:44px">Tampilkan</button>
  <button class="btn line" type="submit" formaction="{{ route('owner.reports.export') }}" style="height:44px">⬇ Ekspor CSV</button>
</form>
<p class="note" style="margin:0 0 12px">Periode: <b>{{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }}</b></p>
@php $done = $s['booking_by_status']['Selesai']; $cancel = $s['booking_by_status']['Dibatalkan']; $t = max($s['booking_total'], 1); @endphp
<div class="grid g4">
  <x-stat label="Total booking" :value="$s['booking_total']" />
  <x-stat label="Selesai" :value="$done" :note="round($done / $t * 100).'% dari total'" />
  <x-stat label="Dibatalkan" :value="$cancel" :note="round($cancel / $t * 100).'%'" :warn="true" />
  <x-stat label="Pendapatan" :value="rupiah($s['revenue'])" :note="$s['paid_count'].' pembayaran Dibayar'" />
</div>
<div class="grid g2 mt2">
  <div class="card"><div class="row sp"><h3>Rekapitulasi booking</h3><span class="sm mut">Total {{ $s['booking_total'] }}</span></div><p class="sub">Berdasarkan status.</p><x-recap :rows="$s['booking_by_status']" /></div>
  <div class="card"><div class="row sp"><h3>Rekapitulasi pembayaran</h3><span class="sm mut">Total {{ $s['payment_total'] }}</span></div><p class="sub">Berdasarkan status.</p><x-recap type="payment" :rows="$s['payment_by_status']" /></div>
</div>
<div class="grid g21 mt2">
  <div class="card"><h3>Booking per bulan</h3><p class="sub">Semua status, 6 bulan terakhir.</p><x-bars :values="$s['monthly']['values']" :labels="$s['monthly']['labels']" /></div>
  <div class="card"><h3>Layanan terlaris</h3><p class="sub">Berdasarkan jumlah booking (tidak termasuk batal).</p>
    @forelse ($s['top_services'] as $t)
      <div style="margin-bottom:12px"><div class="row sp sm"><span>{{ $t['name'] }}</span><b>{{ $t['total'] }} · {{ $t['percent'] }}%</b></div><div class="hbar"><i style="width:{{ $t['percent'] }}%"></i></div></div>
    @empty
      <x-empty icon="✂️" title="Belum ada data" />
    @endforelse
  </div>
</div>
<p class="note">Pendapatan = jumlah pembayaran berstatus Dibayar pada periode terpilih. Laporan dihitung langsung dari data transaksi.</p>
@endsection
