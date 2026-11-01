@extends('layouts.dashboard')
@section('title', 'Dashboard Bisnis')
@section('heading', 'Dashboard analitik bisnis')
@section('subtitle', $business->business_name.' · Periode: '.$from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y'))

@section('content')
{{-- FILTER PERIODE ANALITIK --}}
<div class="card" style="margin-bottom:18px;padding:16px">
  <div class="row sp wrapf" style="gap:12px">
    <div class="tabs" style="margin:0">
      @foreach (\App\Services\ReportService::PERIODS as $pKey => $pLabel)
        @if ($pKey !== 'custom')
          <a href="{{ route('owner.dashboard', ['period' => $pKey]) }}" style="text-decoration:none">
            <button type="button" class="{{ ($period ?? 'month') === $pKey ? 'on' : '' }}">{{ $pLabel }}</button>
          </a>
        @endif
      @endforeach
    </div>

    <form method="GET" action="{{ route('owner.dashboard') }}" class="row wrapf" style="align-items:flex-end;gap:8px">
      <input type="hidden" name="period" value="custom">
      <label class="fld"><input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="Tanggal mulai"></label>
      <label class="fld"><input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="Tanggal akhir"></label>
      <button class="btn sm line" type="submit" style="height:40px">Terapkan rentang</button>
    </form>
  </div>
</div>

{{-- KARTU STATISTIK UTAMA --}}
<div class="grid g4">
  <x-stat label="Booking (periode)" :value="$s['booking_total']" :note="$todayCount.' jadwal hari ini ('.$todayWaiting.' menunggu)'" />
  <x-stat label="Pendapatan terverifikasi" :value="rupiah($s['revenue'])" :note="$s['paid_count'].' transaksi Dibayar'" />
  <x-stat label="Pelanggan (baru / berulang)" :value="$s['customers_new'].' / '.$s['customers_repeat']" note="aktif pada periode ini" />
  <x-stat label="Rating bisnis" :value="$s['rating_count'] > 0 ? number_format((float) $s['rating_avg'], 1, ',', '.').' ★' : 'Belum ada'" :note="$s['rating_count'].' ulasan sah · '.$pendingPayments.' perlu verifikasi bayar'" :warn="$pendingPayments > 0" />
</div>

{{-- GRAFIK PENDAPATAN & JUMLAH BOOKING --}}
<div class="grid g2 mt2">
  <div class="card">
    <h3>Grafik pendapatan terverifikasi</h3>
    <p class="sub">Berdasarkan tanggal pembayaran Dibayar (dalam ribu rupiah).</p>
    <x-bars :values="$chart['values']" :labels="$chart['labels']" :decimals="1" />
  </div>
  <div class="card">
    <h3>Grafik jumlah booking</h3>
    <p class="sub">Berdasarkan tanggal pembuatan booking pada periode terpilih.</p>
    <x-bars :values="$bookingChart['values']" :labels="$bookingChart['labels']" />
  </div>
</div>

{{-- REKAP STATUS & LAYANAN POPULER --}}
<div class="grid g3 mt2">
  <div class="card">
    <div class="row sp"><h3>Booking per status</h3><span class="sm mut">Total {{ $s['booking_total'] }}</span></div>
    <p class="sub">Pada periode terpilih.</p>
    <x-recap :rows="$s['booking_by_status']" />
  </div>

  <div class="card">
    <h3>Layanan paling populer</h3>
    <p class="sub">Berdasarkan frekuensi booking (tidak termasuk batal).</p>
    @forelse ($s['top_services'] as $t)
      <div style="margin-bottom:12px">
        <div class="row sp sm"><span>{{ $t['name'] }}</span><b>{{ $t['total'] }} · {{ $t['percent'] }}%</b></div>
        <div class="hbar" style="margin-top:4px"><i style="width:{{ $t['percent'] }}%"></i></div>
      </div>
    @empty
      <x-empty icon="✂️" title="Belum ada data layanan pada periode ini" />
    @endforelse
  </div>

  <div class="card">
    <h3>Perlu tindakan segera</h3>
    <p class="sub">Selesaikan agar pelanggan tidak menunggu.</p>
    @if ($todoWaiting)<div class="item"><div><div class="b sm">Konfirmasi booking</div><small class="mut">{{ $todoWaiting->customer->name }} · {{ $todoWaiting->items->pluck('service.name')->implode(', ') }}</small></div><a class="btn sm" href="{{ route('owner.bookings.show', $todoWaiting->id) }}">Buka</a></div>@endif
    @if ($todoPayment)<div class="item"><div><div class="b sm">Verifikasi pembayaran</div><small class="mut">{{ $todoPayment->booking->customer->name }} · {{ rupiah($todoPayment->amount) }}</small></div><a class="btn sm" href="{{ route('owner.payments.show', $todoPayment->id) }}">Buka</a></div>@endif
    @if ($todoProgress)<div class="item"><div><div class="b sm">Selesaikan layanan</div><small class="mut">{{ $todoProgress->customer->name }} · {{ $todoProgress->items->pluck('service.name')->implode(', ') }}</small></div><a class="btn sm line" href="{{ route('owner.bookings.show', $todoProgress->id) }}">Buka</a></div>@endif
    @if (! $todoWaiting && ! $todoPayment && ! $todoProgress)<x-empty icon="🎉" title="Semua beres" text="Tidak ada tindakan yang tertunda." />@endif
  </div>
</div>

{{-- RINGKASAN BOOKING YANG AKAN DATANG --}}
<div class="sec-t">🗓️ Ringkasan booking yang akan datang</div>
@if ($upcoming->isEmpty())
  <div class="card"><x-empty icon="🗓️" title="Tidak ada jadwal mendatang" text="Booking aktif mulai hari ini dan seterusnya akan muncul di sini." /></div>
@else
  <div class="tw"><table><thead><tr><th>Jadwal</th><th>Invoice</th><th>Pelanggan</th><th>Hewan</th><th>Layanan</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach ($upcoming as $ub)
      <tr>
        <td class="nw">{{ $ub->schedule_label }}</td>
        <td><a class="gold-t" href="{{ route('owner.bookings.show', $ub->id) }}">{{ $ub->invoice_number }}</a></td>
        <td>{{ $ub->customer->name }}</td>
        <td>{{ $ub->pet->emoji }} {{ $ub->pet->name }}</td>
        <td>{{ $ub->items->pluck('service.name')->implode(', ') }}</td>
        <td><x-status :status="$ub->status" /></td>
        <td><a class="btn sm line" href="{{ route('owner.bookings.show', $ub->id) }}">Kelola</a></td>
      </tr>
    @endforeach
  </tbody></table></div>
@endif

<div class="sec-t">Booking terbaru</div>
@if ($recent->isEmpty())
  <div class="card"><x-empty icon="📅" title="Belum ada booking masuk" /></div>
@else
  <div class="tw"><table><thead><tr><th>Invoice</th><th>Pelanggan</th><th>Hewan</th><th>Layanan</th><th>Status</th><th>Pembayaran</th></tr></thead><tbody>
    @foreach ($recent as $b)
      <tr><td><a class="gold-t" href="{{ route('owner.bookings.show', $b->id) }}">{{ $b->invoice_number }}</a></td><td>{{ $b->customer->name }}</td><td>{{ $b->pet->emoji }} {{ $b->pet->name }}</td><td>{{ $b->items->pluck('service.name')->implode(', ') }}</td><td><x-status :status="$b->status" /></td><td><x-pay :status="$b->payment->status" /></td></tr>
    @endforeach
  </tbody></table></div>
@endif
@endsection
