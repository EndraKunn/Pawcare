@extends('layouts.public')
@section('title', 'Daftar Pelanggan')

@section('bare')
<div class="login">
  <div class="l"><div style="font-size:64px">🐈</div><h1 style="margin-top:14px">Buat akun pelanggan</h1>
    <p class="mut" style="max-width:340px">Daftar gratis, tambahkan hewan kesayangan, lalu booking layanan dari berbagai mitra.</p></div>
  <div class="r">
    <form class="form" method="POST" action="{{ route('register.customer.store') }}">
      @csrf
      <x-field name="name" label="Nama lengkap" placeholder="Nama Anda" required />
      <div class="form f2">
        <x-field name="email" label="Email" type="email" placeholder="nama@email.com" required />
        <x-field name="phone" label="Nomor telepon" placeholder="08xx xxxx xxxx" required />
      </div>
      <div class="form f2">
        <x-field name="password" label="Password" type="password" placeholder="Minimal 8 karakter" hint="Disimpan dalam bentuk hash, bukan teks biasa" required />
        <x-field name="password_confirmation" label="Ulangi password" type="password" required />
      </div>
      <button class="btn block lg" type="submit">Buat akun</button>
    </form>
    <p class="note">Sudah punya akun? <a href="{{ route('login') }}" class="gold-t">Masuk</a> · Ingin menjadi mitra? <a href="{{ route('register.owner') }}" class="gold-t">Daftar mitra bisnis</a></p>
  </div>
</div>
@endsection
