@extends('layouts.public')
@section('title', $business->business_name)

@section('content')
<section class="cover">
  <x-photo :src="$business->cover_url" :fallback="$business->cover_fallback_url" :alt="'Foto sampul '.$business->business_name" :eager="true" />
  <div class="shade"></div>
  <div class="info">
    <div>
      <h1>{{ $business->business_name }}</h1>
      <p class="sm" style="margin:0 0 3px">📍 {{ $business->address ?? '-' }}</p>
      <p class="sm mut" style="margin:0">
        👤 Dikelola oleh {{ $business->owner->name }}
        @if (($ratingSummary['count'] ?? 0) > 0)
          · <span class="gold-t b">★ {{ number_format((float) $ratingSummary['avg'], 1, ',', '.') }}</span> ({{ $ratingSummary['count'] }} ulasan)
        @else
          · Belum ada ulasan
        @endif
      </p>
      @if (! empty($business->accepted_species_list))
        <div class="chips" style="gap:6px;margin-top:8px">
          @foreach ($business->accepted_species_list as $sp)
            <span class="pill gray" style="font-size:11.5px;padding:3px 10px">🐾 {{ $sp }}</span>
          @endforeach
        </div>
      @endif
    </div>
    <div class="row wrapf" style="gap:10px">
      @if (auth()->check() && auth()->user()->isCustomer())
        <form method="POST" action="{{ route('customer.chat.start') }}" class="inline">
          @csrf
          <input type="hidden" name="business_id" value="{{ $business->id }}">
          <button class="btn line" type="submit">💬 Hubungi bisnis</button>
        </form>
      @endif
      @if (! auth()->check() || auth()->user()->isCustomer())
        <a class="btn" href="{{ route('customer.bookings.create', ['business' => $business->id]) }}">Booking sekarang</a>
      @endif
    </div>
  </div>
</section>
@if ($business->description)
  <div class="card mt"><p style="margin:0">{{ $business->description }}</p></div>
@endif

@php
    $dayNames = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    $hoursByDay = $business->operatingHours->keyBy('day_of_week');

    $upcomingClosures = $business->closures->filter(
        fn ($closure) =>
            $closure->closed_date &&
            $closure->closed_date->toDateString() >= now()->toDateString()
    );
@endphp

<div class="card mt">
    <h3>🕒 Jam operasional</h3>
    <p class="sub">
        Jadwal buka dan tutup {{ $business->business_name }}.
    </p>

    <div class="tw">
        <table>
            <thead>
                <tr>
                    <th>Hari</th>
                    <th>Status</th>
                    <th>Jam buka</th>
                    <th>Jam tutup</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($dayNames as $day => $label)
                    @php
                        $slot = $hoursByDay->get($day);
                    @endphp

                    <tr>
                        <td><strong>{{ $label }}</strong></td>

                        @if (! $slot)
                            <td class="mut">Belum diatur</td>
                            <td>—</td>
                            <td>—</td>
                        @elseif ($slot->is_closed)
                            <td style="color:var(--bad)">Tutup</td>
                            <td>—</td>
                            <td>—</td>
                        @else
                            <td style="color:var(--ok)">Buka</td>
                            <td>{{ substr((string) $slot->opens_at, 0, 5) }}</td>
                            <td>{{ substr((string) $slot->closes_at, 0, 5) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($upcomingClosures->isNotEmpty())
        <h3 style="margin-top:20px">📅 Hari libur khusus</h3>

        @foreach ($upcomingClosures as $closure)
            <div class="item">
                <div>
                    <strong>
                        {{ $closure->closed_date->format('d M Y') }}
                    </strong>

                    <small class="mut" style="display:block">
                        {{ $closure->reason ?: 'Bisnis tutup pada tanggal ini' }}
                    </small>
                </div>

                <span class="pill bad">Tutup</span>
            </div>
        @endforeach
    @endif
</div>

<div class="sec-t">Layanan tersedia<small>Hanya layanan milik {{ $business->business_name }} — bisnis lain punya daftar &amp; harga sendiri</small></div>
@if ($services->isEmpty())
  <div class="card"><x-empty icon="✂️" title="Belum ada layanan aktif" /></div>
@else
  <div class="tw"><table>
    <thead><tr><th>Layanan</th><th>Jenis</th><th>Keterangan</th><th class="num">Harga</th><th></th></tr></thead>
    <tbody>
      @foreach ($services as $s)
        <tr>
          <td><div class="row"><x-photo class="thumb" :src="$s->image_url" :fallback="$s->image_fallback_url" :alt="$s->category" /><b>{{ $s->name }}</b></div></td><td>{{ $s->category }}</td><td>{{ $s->description ?? '-' }}</td>
          <td class="num nw">{{ rupiah($s->price) }} <small class="mut">/ {{ $s->unit }}</small></td>
          <td class="num">@if (! auth()->check() || auth()->user()->isCustomer())<a class="btn sm" href="{{ route('customer.bookings.create', ['business' => $business->id, 'service' => $s->id]) }}">Pilih</a>@endif</td>
        </tr>
      @endforeach
    </tbody></table></div>
@endif

{{-- RATING DAN ULASAN PELANGGAN --}}
<div class="sec-t">⭐ Rating &amp; Ulasan Pelanggan<small>Dihitung dari booking yang telah selesai</small></div>
<div class="grid g12">
  <div class="card">
    <h3>Ringkasan penilaian</h3>
    @if (($ratingSummary['count'] ?? 0) === 0)
      <p class="sub" style="margin-top:6px">Belum ada penilaian untuk bisnis ini.</p>
      <div style="font-size:34px;font-weight:700;color:var(--mut)">— / 5</div>
    @else
      <div class="row" style="gap:14px;margin:10px 0 16px">
        <div style="font-size:40px;font-weight:800;color:var(--gold);line-height:1">{{ number_format((float) $ratingSummary['avg'], 1, ',', '.') }}</div>
        <div>
          <div class="gold-t" style="font-size:18px;letter-spacing:2px">{{ str_repeat('★', (int) round($ratingSummary['avg'])).str_repeat('☆', 5 - (int) round($ratingSummary['avg'])) }}</div>
          <small class="mut">Berdasarkan {{ $ratingSummary['count'] }} ulasan terverifikasi</small>
        </div>
      </div>

      @php $totalRev = max(1, (int) $ratingSummary['count']); @endphp
      @foreach ([5, 4, 3, 2, 1] as $star)
        @php
          $cnt = (int) ($ratingSummary['distribution'][$star] ?? 0);
          $pct = round($cnt / $totalRev * 100);
        @endphp
        <div style="margin-bottom:8px">
          <div class="row sp sm">
            <span>{{ $star }} ★</span>
            <span class="mut">{{ $cnt }} ({{ $pct }}%)</span>
          </div>
          <div class="hbar" style="margin-top:4px"><i style="width:{{ $pct }}%"></i></div>
        </div>
      @endforeach
    @endif
  </div>

  <div class="card">
    <h3>Daftar ulasan pelanggan</h3>
    @if ($reviews instanceof \Illuminate\Support\Collection ? $reviews->isEmpty() : $reviews->count() === 0)
      <x-empty icon="⭐" title="Belum ada ulasan" text="Jadilah pelanggan pertama yang memberikan ulasan setelah layanan selesai." />
    @else
      @foreach ($reviews as $rev)
        <div class="item" style="flex-direction:column;align-items:stretch;gap:6px;padding:14px 0">
          <div class="row sp wrapf">
            <div>
              <strong>{{ $rev->customer_public_name }}</strong>
              <span class="gold-t b" style="margin-left:8px">{{ $rev->stars_label }} ({{ $rev->rating }}/5)</span>
            </div>
            <small class="mut">{{ $rev->created_at?->translatedFormat('d M Y') }}</small>
          </div>
          @if ($rev->booking && $rev->booking->items->isNotEmpty())
            <small class="mut">Layanan: {{ $rev->booking->items->map(fn ($i) => $i->service?->name)->filter()->implode(', ') }}</small>
          @endif
          <p style="margin:4px 0 0">{{ $rev->comment ?: 'Pelanggan memberikan rating tanpa komentar tertulis.' }}</p>

          @if ($rev->reply)
            <div style="margin-top:8px;padding:10px 14px;border-left:3px solid var(--gold);background:rgba(224,182,74,.08);border-radius:8px">
              <small class="gold-t b" style="display:block">Tanggapan {{ $business->business_name }} · {{ $rev->reply->created_at?->translatedFormat('d M Y') }}</small>
              <p class="sm" style="margin:4px 0 0">{{ $rev->reply->reply }}</p>
            </div>
          @endif
        </div>
      @endforeach

      @if (method_exists($reviews, 'links'))
        <div class="mt">{{ $reviews->links() }}</div>
      @endif
    @endif
  </div>
</div>
@endsection
