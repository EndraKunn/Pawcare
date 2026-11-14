@extends('layouts.dashboard')
@section('title', 'Cek Slot Booking')
@section('heading', 'Cek slot & pre-order')
@section('subtitle', 'Pilih bisnis, tanggal, dan layanan untuk melihat jadwal yang tersedia.')

@section('content')
<div class="card">
  <form method="GET" action="{{ route('customer.jeni.calendar.index') }}" class="form f2">
    <label class="fld">
      <span>Bisnis layanan</span>
      <select name="business" required>
        <option value="">Pilih bisnis</option>
        @foreach ($businesses as $biz)
          <option value="{{ $biz->id }}" @selected((int) old('business', request('business')) === $biz->id)>{{ $biz->business_name }}</option>
        @endforeach
      </select>
    </label>
    <label class="fld">
      <span>Tanggal booking</span>
      <input type="date" name="date" min="{{ now()->toDateString() }}" value="{{ $date }}" required>
    </label>
    @if ($business)
      <label class="fld">
        <span>Layanan (untuk estimasi durasi)</span>
        <select name="service">
          <option value="0">Semua layanan · estimasi 60 menit</option>
          @foreach ($services as $svc)
            <option value="{{ $svc->id }}" @selected($serviceId === $svc->id)>{{ $svc->name }} ({{ $svc->duration_minutes ?? 60 }} menit)</option>
          @endforeach
        </select>
      </label>
    @endif
    <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Cek ketersediaan</button></div>
  </form>
</div>

@if ($business && $slotInfo)
  <div class="card mt">
    <div class="row sp wrapf">
      <h3>Slot {{ $business->business_name }} · {{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}</h3>
      @if ($slotInfo['is_preorder'] ?? false)<span class="pill wait">Pre-order</span>@endif
    </div>
    @if (! $slotInfo['is_open'])
      <p class="err-t">{{ $slotInfo['reason'] }}</p>
    @else
      <p class="sub">Jam buka {{ $slotInfo['opens_at'] }}–{{ $slotInfo['closes_at'] }} · Kapasitas {{ $slotInfo['slot_capacity'] }} booking per slot.</p>
      <div class="chips mt" style="gap:8px">
        @foreach ($slotInfo['slots'] as $slot)
          @if ($slot['is_break'])
            <span class="pill gray">☕ {{ $slot['time'] }} · Istirahat</span>
          @elseif ($slot['available'])
            <span class="pill done">✔ {{ $slot['time'] }} · Sisa {{ $slot['remaining'] }}</span>
          @else
            <span class="pill bad">✖ {{ $slot['time'] }} · Penuh</span>
          @endif
        @endforeach
      </div>
      <p class="note mt">Ketersediaan bersifat sementara dan akan dicek kembali oleh server ketika booking dibuat.</p>
      <a class="btn mt" href="{{ route('customer.bookings.create', ['business' => $business->id, 'date' => $date, 'service' => $serviceId ?: null]) }}">Lanjut pilih hewan &amp; buat booking</a>
    @endif
  </div>
@endif
@endsection
