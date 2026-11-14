@extends('layouts.dashboard')
@section('title', 'Booking '.$business->business_name)
@section('heading', 'Booking layanan')
@section('subtitle', $business->business_name.' · total dihitung otomatis oleh server.')
@section('actions')<a class="btn line" href="{{ route('customer.bookings.cart') }}">🛒 Lihat keranjang</a>@endsection

@section('content')
@php
  $selected = collect(old('services', $preselect ? [$preselect] : []))->map(fn ($v) => (int) $v)->all();
  $oldPet = old('pet_id', $pets->count() === 1 ? $pets->first()->id : null);
  $bookingDateVal = old('booking_date', $selectedDate ?? now()->toDateString());
  $bookingTimeVal = old('booking_time', '09:00');
@endphp
@if ($services->isEmpty())
  <div class="card"><x-empty icon="✂️" title="Bisnis ini belum memiliki layanan aktif" /></div>
@else
<form method="POST" action="{{ route('customer.bookings.store') }}">
  @csrf
  <input type="hidden" name="business_id" value="{{ $business->id }}">
  <div class="grid g21" id="bk">
    <div>
      <div class="card"><h3>1. Pilih hewan</h3><p class="sub">Hanya hewan aktif milik Anda.</p>
        <div class="pick">
          @foreach ($pets as $p)
            <label><input type="radio" name="pet_id" value="{{ $p->id }}" @checked((int) $oldPet === $p->id)><div class="pk"><x-photo class="pk-ph" :src="$p->photo_url" :fallback="$p->photo_fallback_url" :alt="$p->name" /><div><b>{{ $p->name }}</b><small>{{ $p->breed ?? $p->species }}</small></div></div></label>
          @endforeach
        </div>
        @error('pet_id')<p class="err-t">{{ $message }}</p>@enderror
      </div>

      <div class="card mt"><h3>2. Pilih layanan</h3><p class="sub">Centang layanan dan atur jumlah hari atau sesi.</p>
        @foreach ($services as $s)
          @php $on = in_array($s->id, $selected, true); @endphp
          <div class="svc {{ $on ? 'on' : '' }}" data-price="{{ $s->price }}" data-name="{{ $s->name }}">
            <input type="checkbox" name="services[]" value="{{ $s->id }}" aria-label="{{ $s->name }}" @checked($on)>
            <div class="i">
              <b>{{ $s->name }}</b>
              <small>{{ rupiah($s->price) }} / {{ $s->unit }} ({{ $s->duration_minutes ?? 60 }} menit)@if($s->description) · {{ $s->description }}@endif</small>
            </div>
            <div class="q"><input type="number" name="qty[{{ $s->id }}]" min="1" max="365" value="{{ old('qty.'.$s->id, 1) }}" aria-label="Jumlah {{ $s->unit }}"></div>
          </div>
        @endforeach
        @error('services')<p class="err-t">{{ $message }}</p>@enderror
        @error('services.*')<p class="err-t">{{ $message }}</p>@enderror
      </div>

      <div class="card mt"><h3>3. Jadwal, ketersediaan slot &amp; catatan</h3>
        <div class="form f2 mt">
          <x-field name="booking_date" label="Tanggal layanan" type="date" :value="$bookingDateVal" :min="now()->toDateString()" hint="Tidak boleh tanggal yang sudah lewat" required />
          <x-field name="booking_time" label="Jam layanan" type="time" :value="$bookingTimeVal" required />
        </div>

        @if (isset($slotInfo))
          <div style="margin-top:14px;padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:var(--field)">
            <div class="row sp wrapf" style="margin-bottom:8px">
              <span class="sm b">Ketersediaan slot ({{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d M Y') }}):</span>
              @if ($slotInfo['is_open'])
                <small class="mut">Jam buka {{ $slotInfo['opens_at'] }}–{{ $slotInfo['closes_at'] }}</small>
              @endif
            </div>
            @if (! $slotInfo['is_open'])
              <p class="sm" style="margin:0;color:var(--bad)">⚠️ {{ $slotInfo['reason'] }}</p>
            @else
              <div class="chips" style="gap:6px">
                @foreach ($slotInfo['slots'] as $slot)
                  @if ($slot['is_break'])
                    <span class="pill gray" style="font-size:11.5px">☕ {{ $slot['time'] }} (Istirahat)</span>
                  @elseif ($slot['available'])
                    <button type="button" class="chip" style="padding:5px 12px;font-size:12px" onclick="document.querySelector('input[name=booking_time]').value='{{ $slot['time'] }}'">
                      ✔ {{ $slot['time'] }} (Sisa {{ $slot['remaining'] }})
                    </button>
                  @else
                    <span class="pill bad" style="font-size:11.5px">✖ {{ $slot['time'] }} (Penuh)</span>
                  @endif
                @endforeach
              </div>
              <small class="mut" style="display:block;margin-top:8px">Klik jam yang tersedia di atas untuk memilih slot secara cepat. Server tetap memvalidasi ulang ketersediaan saat booking disimpan.</small>
            @endif
          </div>
        @endif

        <div class="mt"><x-field name="notes" label="Catatan untuk bisnis (opsional)" type="textarea" placeholder="Contoh: alergi makanan tertentu" /></div>
      </div>
    </div>
    <div>
      <div class="card gold" style="position:sticky;top:12px">
        <h3>Ringkasan booking</h3><p class="sub">{{ $business->business_name }}</p>
        <div id="sumList"></div>
        <div class="total"><span>Total (perkiraan)</span><b id="sumTotal">Rp 0</b></div>
        <p class="note">Total final dan ketersediaan slot diverifikasi ulang oleh server saat booking disimpan.</p>
        <button class="btn block lg mt" type="submit">Buat booking sekarang</button>
        <button class="btn block line mt" type="submit" formaction="{{ route('customer.bookings.cart.add') }}">Tambah ke keranjang</button>
      </div>
    </div>
  </div>
</form>
@endif
@endsection
