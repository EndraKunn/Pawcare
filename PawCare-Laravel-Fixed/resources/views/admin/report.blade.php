@extends('layouts.dashboard')
@section('title', 'Laporan Sistem')
@section('heading', 'Laporan sistem')
@section('subtitle', 'Rekapitulasi seluruh platform dari data pengguna, bisnis, layanan, booking, dan pembayaran.')
@section('actions')
  <a class="btn line" href="{{ route('admin.reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">⬇ Ekspor CSV</a>
@endsection

@section('content')
<form method="GET" action="{{ route('admin.reports') }}" class="row wrapf" style="margin-bottom:6px;align-items:flex-end">
  <x-field name="from" label="Dari" type="date" :value="$from->toDateString()" />
  <x-field name="to" label="Sampai" type="date" :value="$to->toDateString()" />
  <button class="btn" type="submit" style="height:44px">Tampilkan</button>
  <button class="btn line" type="submit" formaction="{{ route('admin.reports.export') }}" style="height:44px">⬇ Ekspor CSV</button>
</form>
<p class="note" style="margin:0 0 6px">Periode: <b>{{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }}</b></p>

<div class="sec-t">Ringkasan platform</div>
<div class="grid g3">
  <x-stat label="Total pengguna" :value="$totals['users']" :note="$totals['customers'].' customer · '.$totals['owners'].' pemilik · '.$totals['admins'].' admin'" />
  <x-stat label="Total pemilik bisnis" :value="$totals['owners']" note="terdaftar di platform" />
  <x-stat label="Total customer" :value="$totals['customers']" />
  <x-stat label="Bisnis aktif / nonaktif" :value="$totals['businesses_active'].' / '.($totals['businesses'] - $totals['businesses_active'])" :warn="true" />
  <x-stat label="Booking (periode)" :value="$s['booking_total']" />
  <x-stat label="Transaksi (periode)" :value="rupiah($s['revenue'])" :note="$s['paid_count'].' pembayaran Dibayar'" />
</div>

<div class="sec-t">Rekapitulasi booking &amp; pembayaran</div>
<div class="grid g2">
  <div class="card"><div class="row sp"><h3>Rekapitulasi booking</h3><span class="sm mut">Total {{ $s['booking_total'] }}</span></div><p class="sub">Berdasarkan status booking.</p><x-recap :rows="$s['booking_by_status']" /></div>
  <div class="card"><div class="row sp"><h3>Rekapitulasi pembayaran</h3><span class="sm mut">Total {{ $s['payment_total'] }}</span></div><p class="sub">Berdasarkan status pembayaran.</p><x-recap type="payment" :rows="$s['payment_by_status']" /></div>
</div>

<div class="sec-t">Grafik</div>
<div class="grid g2">
  <div class="card"><h3>Booking per bulan (seluruh platform)</h3><p class="sub">Semua status, 6 bulan terakhir.</p><x-bars :values="$s['monthly']['values']" :labels="$s['monthly']['labels']" /></div>
  <div class="card"><h3>Total transaksi per bisnis</h3><p class="sub">Dalam ribu rupiah, 6 bisnis teratas pada periode.</p>
    @php $topRows = $rows->sortByDesc('revenue')->take(6)->values(); @endphp
    <x-bars :values="$topRows->map(fn ($r) => $r->revenue / 1000)->all()" :labels="$topRows->map(fn ($r) => \Illuminate\Support\Str::limit($r->business_name, 8, ''))->all()" />
  </div>
</div>

<div class="sec-t">Laporan per bisnis</div>
<div class="tw"><table><thead><tr><th>Bisnis</th><th>Pemilik</th><th class="num">Layanan</th><th class="num">Booking</th><th class="num">Pembayaran berhasil</th><th class="num">Total transaksi</th></tr></thead><tbody>
  @foreach ($rows as $r)
    <tr><td><div class="row"><span style="font-size:20px">{{ $r->icon }}</span><b>{{ $r->business_name }}</b>@unless($r->status)<x-status status="Suspend" />@endunless</div></td><td>{{ $r->owner->name }}</td><td class="num">{{ $r->services_count }}</td><td class="num">{{ $r->bookings_count }}</td><td class="num">{{ $r->paid_count }}</td><td class="num nw">{{ rupiah($r->revenue) }}</td></tr>
  @endforeach
  <tr><td><b>Total platform</b></td><td></td><td class="num"><b>{{ $rows->sum('services_count') }}</b></td><td class="num"><b>{{ $rows->sum('bookings_count') }}</b></td><td class="num"><b>{{ $rows->sum('paid_count') }}</b></td><td class="num"><b class="gold-t">{{ rupiah($rows->sum('revenue')) }}</b></td></tr>
</tbody></table></div>
<p class="note">Total transaksi = jumlah pembayaran berstatus Dibayar pada periode terpilih. Data per bisnis dipisahkan; Admin hanya melihat rekapitulasinya.</p>
@endsection
