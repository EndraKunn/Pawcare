@extends('layouts.dashboard')
@section('title', 'Kalender & Slot Booking')
@section('heading', 'Kalender booking & ketersediaan slot')
@section('subtitle', $business->business_name.' · '.$rangeStart->translatedFormat('d M Y').' s/d '.$rangeEnd->translatedFormat('d M Y'))
@section('actions')
  <a class="btn line" href="{{ route('owner.business.edit') }}">⚙️ Pengaturan jam &amp; kapasitas slot</a>
  <a class="btn line" href="{{ route('owner.jeni.preorder.edit') }}">🕒 Pengaturan pre-order</a>
@endsection

@section('content')
<div class="row sp wrapf" style="margin-bottom:16px;gap:12px">
  <div class="tabs" style="margin:0">
    @foreach (['day' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan'] as $mKey => $mLabel)
      <a href="{{ route('owner.calendar.index', ['mode' => $mKey, 'date' => $anchorDate->toDateString(), 'service_id' => $serviceId, 'status' => $statusFilter]) }}" style="text-decoration:none">
        <button type="button" class="{{ $viewMode === $mKey ? 'on' : '' }}">{{ $mLabel }}</button>
      </a>
    @endforeach
  </div>

  <div class="row wrapf" style="gap:8px">
    <a class="btn sm line" href="{{ route('owner.calendar.index', ['mode' => $viewMode, 'date' => $prevDate, 'service_id' => $serviceId, 'status' => $statusFilter]) }}">‹ Sebelumnya</a>
    <a class="btn sm line" href="{{ route('owner.calendar.index', ['mode' => $viewMode, 'date' => now()->toDateString(), 'service_id' => $serviceId, 'status' => $statusFilter]) }}">Hari Ini</a>
    <a class="btn sm line" href="{{ route('owner.calendar.index', ['mode' => $viewMode, 'date' => $nextDate, 'service_id' => $serviceId, 'status' => $statusFilter]) }}">Berikutnya ›</a>
  </div>
</div>

<form method="GET" action="{{ route('owner.calendar.index') }}" class="row wrapf" style="margin-bottom:18px;align-items:flex-end">
  <input type="hidden" name="mode" value="{{ $viewMode }}">
  <label class="fld">
    <span>Tanggal acuan</span>
    <input type="date" name="date" value="{{ $anchorDate->toDateString() }}">
  </label>
  <label class="fld">
    <span>Filter layanan</span>
    <select name="service_id">
      <option value="0">Semua layanan</option>
      @foreach ($services as $svc)
        <option value="{{ $svc->id }}" @selected($serviceId === $svc->id)>{{ $svc->name }}</option>
      @endforeach
    </select>
  </label>
  <label class="fld">
    <span>Filter status booking</span>
    <select name="status">
      <option value="">Semua status</option>
      @foreach (\App\Models\Booking::STATUSES as $st)
        <option value="{{ $st }}" @selected($statusFilter === $st)>{{ $st }}</option>
      @endforeach
    </select>
  </label>
  <button class="btn" type="submit" style="height:44px">Terapkan</button>
</form>

{{-- RINGKASAN SLOT PADA TANGGAL ACUAN --}}
<div class="card" style="margin-bottom:20px">
  <div class="row sp wrapf">
    <div>
      <h3>🕒 Ketersediaan slot tanggal {{ $anchorDate->translatedFormat('l, d M Y') }}</h3>
      <p class="sub" style="margin:2px 0 0">
        @if (! $dailySlotInfo['is_open'])
          <span style="color:var(--bad)">{{ $dailySlotInfo['reason'] }}</span>
        @else
          Jam operasional {{ $dailySlotInfo['opens_at'] }}–{{ $dailySlotInfo['closes_at'] }}
          · Kapasitas {{ $dailySlotInfo['slot_capacity'] }} booking per slot ({{ $dailySlotInfo['slot_duration_minutes'] }} menit)
          @if ($dailySlotInfo['break_starts_at'] && $dailySlotInfo['break_ends_at'])
            · Istirahat {{ $dailySlotInfo['break_starts_at'] }}–{{ $dailySlotInfo['break_ends_at'] }}
          @endif
        @endif
      </p>
    </div>
  </div>

  @if ($dailySlotInfo['is_open'] && ! empty($dailySlotInfo['slots']))
    <div class="chips mt" style="gap:8px">
      @foreach ($dailySlotInfo['slots'] as $slot)
        @if ($slot['is_break'])
          <span class="pill gray" title="Waktu istirahat">☕ {{ $slot['time'] }}–{{ $slot['end_time'] }} · Istirahat</span>
        @elseif ($slot['available'])
          <span class="pill done">✔ {{ $slot['time'] }}–{{ $slot['end_time'] }} · Terpakai {{ $slot['used'] }}/{{ $slot['capacity'] }}</span>
        @else
          <span class="pill bad">✖ {{ $slot['time'] }}–{{ $slot['end_time'] }} · Penuh ({{ $slot['used'] }}/{{ $slot['capacity'] }})</span>
        @endif
      @endforeach
    </div>
  @endif
</div>

{{-- TAMPILAN KALENDER & DAFTAR RESPONSIF --}}
@if ($bookings->isEmpty())
  <div class="card">
    <x-empty icon="🗓️" title="Tidak ada jadwal booking pada periode ini" text="Booking yang sesuai filter akan tampil pada kalender di bawah ini." />
  </div>
@else
  <div class="grid {{ $viewMode === 'day' ? '' : 'g3' }}">
    @foreach ($days as $dayItem)
      @if ($viewMode === 'day' || $dayItem['bookings']->isNotEmpty() || $viewMode === 'week')
        <div class="card">
          <div class="row sp" style="margin-bottom:10px">
            <strong>{{ $dayItem['date']->translatedFormat('D, d M Y') }}</strong>
            <span class="pill gray">{{ $dayItem['bookings']->count() }} booking</span>
          </div>

          @forelse ($dayItem['bookings'] as $b)
            <div class="item" style="flex-direction:column;align-items:stretch;gap:6px;padding:12px 0">
              <div class="row sp wrapf">
                <span class="b gold-t">{{ $b->time_label }}–{{ $b->end_time_label }}</span>
                <x-status :status="$b->status" />
                @if ($b->is_preorder)<span class="pill wait">Pre-order</span>@endif
              </div>
              <div class="sm">
                <b>{{ $b->pet->emoji ?? '🐾' }} {{ $b->pet->name ?? '-' }}</b> ({{ $b->pet->species ?? '-' }}) · Pelanggan: {{ $b->customer->name ?? '-' }}
              </div>
              <small class="mut">{{ $b->items->map(fn ($i) => ($i->service->name ?? 'Layanan').' ×'.$i->quantity)->implode(', ') }}</small>
              <div class="row sp" style="margin-top:4px">
                <small class="mut">{{ $b->invoice_number }}</small>
                <a class="btn sm line" href="{{ route('owner.bookings.show', $b->id) }}">Detail</a>
              </div>
            </div>
          @empty
            <p class="sm mut" style="margin:6px 0 0">Tidak ada jadwal booking.</p>
          @endforelse
        </div>
      @endif
    @endforeach
  </div>
@endif
@endsection
