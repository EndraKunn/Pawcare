@extends('layouts.dashboard')
@section('title', 'Ubah Layanan')
@section('heading', 'Ubah layanan')
@section('subtitle', $business->business_name)

@section('content')
<div class="card" style="max-width:640px">
  <p class="note" style="margin-top:0">Perubahan harga tidak mengubah transaksi lama (harga disimpan sebagai snapshot pada booking).</p>
  <form method="POST" action="{{ route('owner.services.update', $service->id) }}" class="form">
    @csrf @method('PUT')
    <x-field name="name" label="Nama layanan" :value="$service->name" required />
    <div class="form f2">
      <x-field name="category" label="Jenis" type="select" :options="array_combine(\App\Models\Service::CATEGORIES, \App\Models\Service::CATEGORIES)" :value="$service->category" required />
      <x-field name="unit" label="Satuan" type="select" :options="['hari' => 'per hari', 'sesi' => 'per sesi']" :value="$service->unit" required />
    </div>
    <div class="form f2">
      <x-field name="price" label="Harga (Rp)" type="number" min="0" step="1" :value="(int) $service->price" required />
      <x-field name="duration_minutes" label="Durasi (menit)" type="number" min="15" max="480" :value="$service->duration_minutes ?? 60" required />
    </div>
    <x-field name="description" label="Deskripsi" type="textarea" :value="$service->description" />
    <label class="check"><input type="hidden" name="status" value="0"><input type="checkbox" name="status" value="1" @checked(old('status', $service->status))> Layanan aktif (dapat dipesan)</label>
    <div class="row" style="justify-content:flex-end"><a class="btn line" href="{{ route('owner.services.index') }}">Batal</a><button class="btn" type="submit">Simpan perubahan</button></div>
  </form>
</div>
@endsection
