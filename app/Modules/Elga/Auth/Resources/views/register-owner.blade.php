@extends('layouts.public')
@section('title', 'Daftar Mitra Bisnis')

@section('bare')
<div class="login">
  <div class="l"><div style="font-size:64px">🏢</div><h1 style="margin-top:14px">Bergabung sebagai mitra</h1>
    <p class="mut" style="max-width:340px">Bisnis Anda mendapat dashboard sendiri. Data bisnis Anda terpisah dari mitra lain.</p>
    <div class="mt"><div class="check">✅ Booking &amp; pembayaran terpusat</div><div class="check">✅ Verifikasi bukti transfer</div><div class="check">✅ Laporan aktivitas bisnis</div></div></div>
  <div class="r">
    <form class="form" method="POST" action="{{ route('register.owner.store') }}">
      @csrf
      <div class="form f2">
        <x-field name="name" label="Nama pemilik bisnis" placeholder="Nama Anda" required />
        <x-field name="business_name" label="Nama bisnis" placeholder="Contoh: Happy Paws" required />
      </div>
      <div class="form f2">
        <x-field name="email" label="Email" type="email" placeholder="bisnis@email.com" required />
        <x-field name="phone" label="Nomor telepon" placeholder="08xx xxxx xxxx" required />
      </div>
      <x-field name="address" label="Alamat bisnis" type="textarea" placeholder="Alamat lengkap bisnis" required />
      <div class="form f2">
        <x-field name="password" label="Password" type="password" placeholder="Minimal 8 karakter" hint="Disimpan dalam bentuk hash, bukan teks biasa" required />
        <x-field name="password_confirmation" label="Ulangi password" type="password" required />
      </div>
      <button class="btn block lg" type="submit">Daftar sebagai mitra</button>
    </form>
    <p class="note">Sudah punya akun? <a href="{{ route('login') }}" class="gold-t">Masuk</a></p>
  </div>
</div>
@endsection
