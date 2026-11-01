@extends('layouts.dashboard')
@section('title', 'Dashboard Admin')
@section('heading', 'Dashboard analitik platform')
@section('subtitle', 'Pemantauan seluruh aktivitas PawCare · Periode: '.$from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y'))

@section('content')
<div class="alert" style="margin-bottom:16px">📊 <span>Angka dihitung dari database aktual. Pendapatan hanya menghitung pembayaran berstatus Dibayar pada periode terpilih.</span></div>

{{-- FILTER PERIODE ANALITIK --}}
<div class="card" style="margin-bottom:18px;padding:16px">
  <div class="row sp wrapf" style="gap:12px">
    <div class="tabs" style="margin:0">
      @foreach (\App\Services\ReportService::PERIODS as $pKey => $pLabel)
        @if ($pKey !== 'custom')
          <a href="{{ route('admin.dashboard', ['period' => $pKey]) }}" style="text-decoration:none">
            <button type="button" class="{{ ($period ?? 'month') === $pKey ? 'on' : '' }}">{{ $pLabel }}</button>
          </a>
        @endif
      @endforeach
    </div>

    <form method="GET" action="{{ route('admin.dashboard') }}" class="row wrapf" style="align-items:flex-end;gap:8px">
      <input type="hidden" name="period" value="custom">
      <label class="fld"><input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="Tanggal mulai"></label>
      <label class="fld"><input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="Tanggal akhir"></label>
      <button class="btn sm line" type="submit" style="height:40px">Terapkan rentang</button>
    </form>
  </div>
</div>

@php
  $cmp = $s['period_comparison'];
  $bkDiffSign = $cmp['booking_diff'] >= 0 ? '+'.$cmp['booking_diff'] : (string) $cmp['booking_diff'];
  $revDiffSign = ($cmp['revenue_diff'] >= 0 ? '+' : '-').rupiah(abs($cmp['revenue_diff']));
  $totalUsersValue = $totals['users'];
  $totalUsersNote = $totals['customers'].' customer · '.$totals['owners'].' pemilik bisnis · '.$totals['admins'].' admin';
  $totalBusinessesValue = $totals['businesses_active'].' / '.$totals['businesses'];
  $totalBusinessesNote = ($totals['businesses'] - $totals['businesses_active']).' disuspend';
  $totalBookingsValue = $totalBookings;
  $totalBookingsNote = $s['booking_total'].' pada periode ('.$bkDiffSign.' vs periode lalu)';
  $totalRevenueValue = rupiah($s['revenue']);
  $totalRevenueNote = $s['paid_count'].' pembayaran Dibayar ('.$revDiffSign.')';
@endphp

<div class="grid g4">
  <x-stat label="Total pengguna" :value="$totalUsersValue" :note="$totalUsersNote" />
  <x-stat label="Bisnis aktif / total" :value="$totalBusinessesValue" :note="$totalBusinessesNote" :warn="true" />
  <x-stat label="Total booking" :value="$totalBookingsValue" :note="$totalBookingsNote" />
  <x-stat label="Pendapatan terverifikasi" :value="$totalRevenueValue" :note="$totalRevenueNote" />
</div>

<div class="grid g2 mt2">
  <div class="card"><div class="row sp"><h3>Booking per status</h3><span class="sm mut">{{ $s['booking_total'] }} pada periode</span></div><p class="sub">Seluruh platform.</p><x-recap :rows="$s['booking_by_status']" /></div>
  <div class="card"><div class="row sp"><h3>Pembayaran per status</h3><span class="sm mut">{{ $pendingPayments }} menunggu verifikasi (total)</span></div><p class="sub">Seluruh platform.</p><x-recap type="payment" :rows="$s['payment_by_status']" /></div>
</div>

<div class="grid g2 mt2">
  <div class="card">
    <h3>Grafik pertumbuhan booking (6 bulan)</h3>
    <p class="sub">Seluruh platform, 6 bulan terakhir.</p>
    <x-bars :values="$s['monthly']['values']" :labels="$s['monthly']['labels']" />
  </div>
  <div class="card">
    <h3>Grafik pendapatan terverifikasi (periode)</h3>
    <p class="sub">Berdasarkan tanggal pelunasan (dalam ribu rupiah).</p>
    <x-bars :values="$s['daily_revenue']['values']" :labels="$s['daily_revenue']['labels']" :decimals="1" />
  </div>
</div>

<div class="grid g3 mt2">
  <div class="card">
    <h3>Bisnis pendapatan tertinggi</h3>
    <p class="sub">Berdasarkan transaksi Dibayar pada periode.</p>
    @forelse ($top as $b)
      <div class="item">
        <div>
          <div class="b sm">{{ $b->business_name }}</div>
          <small class="mut">{{ $b->owner->name }} · {{ $b->bookings_count }} booking</small>
        </div>
        <b class="gold-t">{{ rupiah($b->revenue) }}</b>
      </div>
    @empty
      <x-empty icon="🏢" title="Belum ada bisnis" />
    @endforelse
  </div>

  <div class="card">
    <h3>Layanan paling banyak digunakan</h3>
    <p class="sub">Berdasarkan jumlah booking di seluruh platform.</p>
    @forelse ($s['top_services'] as $t)
      <div style="margin-bottom:12px">
        <div class="row sp sm"><span>{{ $t['name'] }}</span><b>{{ $t['total'] }} · {{ $t['percent'] }}%</b></div>
        <div class="hbar" style="margin-top:4px"><i style="width:{{ $t['percent'] }}%"></i></div>
      </div>
    @empty
      <x-empty icon="✂️" title="Belum ada data layanan" />
    @endforelse
  </div>

  <div class="card">
    <h3>Rating &amp; ulasan platform</h3>
    <p class="sub">Dari seluruh booking berstatus Selesai.</p>
    <div class="row" style="gap:12px;margin:8px 0 14px">
      <div style="font-size:34px;font-weight:800;color:var(--gold)">{{ $s['rating_count'] > 0 ? number_format((float) $s['rating_avg'], 1, ',', '.') : '—' }}</div>
      <div>
        <div class="gold-t">★ Rata-rata Platform</div>
        <small class="mut">{{ $s['rating_count'] }} ulasan terverifikasi</small>
      </div>
    </div>
    @php $totR = max(1, (int) $s['rating_count']); @endphp
    @foreach ([5, 4, 3, 2, 1] as $st)
      @php $c = (int) ($s['rating_distribution'][$st] ?? 0); $p = round($c / $totR * 100); @endphp
      <div style="margin-bottom:6px">
        <div class="row sp sm"><span>{{ $st }} ★</span><span class="mut">{{ $c }} ({{ $p }}%)</span></div>
        <div class="hbar" style="margin-top:3px"><i style="width:{{ $p }}%"></i></div>
      </div>
    @endforeach
  </div>
</div>
@endsection
