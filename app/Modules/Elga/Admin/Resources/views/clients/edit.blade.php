@extends('layouts.dashboard')
@section('title', 'Ubah Bisnis')
@section('heading', 'Perbarui informasi bisnis')
@section('subtitle', $business->business_name.' · pemilik '.$business->owner->name)

@section('content')
<div class="card" style="max-width:640px">
  <form method="POST" action="{{ route('admin.clients.update', $business->id) }}" class="form">
    @csrf @method('PUT')
    <x-field name="business_name" label="Nama bisnis" :value="$business->business_name" required />
    <x-field name="address" label="Alamat" type="textarea" :value="$business->address" required />
    <x-field name="description" label="Deskripsi" type="textarea" :value="$business->description" />
    <div class="row" style="justify-content:flex-end"><a class="btn line" href="{{ route('admin.clients.show', $business->id) }}">Batal</a><button class="btn" type="submit">Simpan</button></div>
  </form>
</div>
@endsection
