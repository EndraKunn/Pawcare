@extends('layouts.dashboard')
@section('title', 'Pengaturan Pre-order')
@section('heading', 'Pengaturan pre-order')
@section('subtitle', $business->business_name.' · reservasi jauh hari dengan kapasitas terkontrol')
@section('actions')
  <a class="btn line" href="{{ route('owner.calendar.index') }}">← Kembali ke kalender</a>
@endsection

@section('content')
<div class="card" style="max-width:740px">
  <h3>Pre-order booking</h3>
  <p class="sub">Booking reguler tersedia hingga 30 hari ke depan. Aktifkan pre-order agar pelanggan dapat memesan lebih awal. Pre-order langsung menempati slot dan mengikuti alur pembayaran serta pembatalan normal.</p>
  <form method="POST" action="{{ route('owner.jeni.preorder.update') }}">
    @csrf
    @method('PUT')
    <div class="fld mt">
      <label for="pre_order_enabled" style="display:flex;align-items:center;gap:10px">
        <input id="pre_order_enabled" type="checkbox" name="pre_order_enabled" value="1" @checked((bool) old('pre_order_enabled', $business->pre_order_enabled))>
        <span>Izinkan pre-order (lebih dari 30 hari sebelum jadwal)</span>
      </label>
      @error('pre_order_enabled')<small class="err-t">{{ $message }}</small>@enderror
    </div>
    <div class="form f2 mt">
      <label class="fld">
        <span>Batas pre-order (hari ke depan)</span>
        <input type="number" name="pre_order_max_days" min="31" max="365" required value="{{ old('pre_order_max_days', $business->pre_order_max_days ?? 180) }}">
        @error('pre_order_max_days')<small class="err-t">{{ $message }}</small>@enderror
      </label>
      <label class="fld">
        <span>Kapasitas pre-order per slot</span>
        <input type="number" name="pre_order_slot_capacity" min="1" max="100" required value="{{ old('pre_order_slot_capacity', $business->pre_order_slot_capacity ?? 5) }}">
        @error('pre_order_slot_capacity')<small class="err-t">{{ $message }}</small>@enderror
      </label>
    </div>
    <p class="note mt">Kapasitas efektif adalah nilai lebih kecil antara kapasitas operasional dan kapasitas pre-order. Booking yang sudah dibuat tidak dihapus jika pengaturan dinonaktifkan.</p>
    <button class="btn mt" type="submit">Simpan pengaturan pre-order</button>
  </form>
</div>
@endsection
