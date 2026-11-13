@extends('layouts.public')
@section('title', 'Masuk')

@section('bare')
<div class="login">
  <div class="l">
    <div style="font-size:64px">🐕‍🦺</div>
    <h1 style="margin-top:14px">Selamat datang kembali</h1>
    <p class="mut" style="max-width:340px">Masuk untuk memantau booking, pembayaran, dan layanan hewan kesayangan atau bisnis Anda.</p>
  </div>
  <div class="r">
    <form class="form" method="POST" action="{{ route('login.attempt') }}">
      @csrf
      @include('partials.flash')
      <x-field name="email" label="Email" type="email" placeholder="nama@email.com" required autofocus />
      <x-field name="password" label="Password" type="password" placeholder="Masukkan password" required />
      <div class="row sp"><label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya</label></div>
      <button class="btn block lg" type="submit">Masuk</button>
    </form>
    <div class="card mt" style="padding:14px 16px">
      <div class="b sm" style="margin-bottom:4px">🔐 Role ditentukan oleh akun, bukan dipilih di formulir</div>
      <p class="sm mut" style="margin:0">Setelah login, Anda otomatis diarahkan ke area sesuai role (Pelanggan, Pemilik Bisnis, atau Admin).</p>
    </div>
    @if (app()->environment('local'))
      <div class="card mt" style="padding:14px 16px">
        <div class="b sm" style="margin-bottom:6px">🧪 Akun demo (lokal) — password: <span class="rolecode">password</span></div>
        <p class="sm mut" style="margin:0"><span class="rolecode">admin@pawcare.test</span> · <span class="rolecode">owner@pawcare.test</span> · <span class="rolecode">customer@pawcare.test</span></p>
      </div>
    @endif
    <hr>
    <p class="sm mut" style="margin:0">Belum punya akun? <a href="{{ route('register.customer') }}" class="gold-t">Daftar sebagai pelanggan</a> atau <a href="{{ route('register.owner') }}" class="gold-t">mitra bisnis</a></p>
  </div>
</div>
@endsection
