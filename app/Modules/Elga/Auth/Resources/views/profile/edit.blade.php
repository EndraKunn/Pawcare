@extends('layouts.dashboard')
@section('title', 'Profil')
@section('heading', 'Profil saya')
@section('subtitle', 'Perbarui informasi akun Anda.')

@section('content')
<div class="grid g12">
  <div class="card" style="text-align:center;align-self:start">
    <div class="av {{ $user->isCustomer() ? 'bl' : '' }}" style="width:84px;height:84px;font-size:28px;margin:0 auto 10px">{{ initials($user->name) }}</div>
    <h3>{{ $user->name }}</h3>
    <p class="sub" style="margin:0">{{ $user->email }}</p>
    <p style="margin:10px 0 0"><span class="rolecode">{{ $user->role }}</span></p>
  </div>
  <div class="card">
    <h3>Informasi akun</h3><p class="sub">Email digunakan untuk masuk.</p>
    <form method="POST" action="{{ route('profile.update') }}" class="form">
      @csrf @method('PUT')
      <div class="form f2">
        <x-field name="name" label="Nama lengkap" :value="$user->name" required />
        <x-field name="phone" label="Nomor telepon" :value="$user->phone" />
      </div>
      <x-field name="email" label="Email" type="email" :value="$user->email" required />
      <div class="row" style="justify-content:flex-end"><button class="btn" type="submit">Simpan perubahan</button></div>
    </form>
    <hr>
    <h3>Ganti password</h3>
    <form method="POST" action="{{ route('profile.password') }}" class="form mt">
      @csrf @method('PUT')
      <x-field name="current_password" label="Password saat ini" type="password" required />
      <div class="form f2">
        <x-field name="password" label="Password baru" type="password" placeholder="Minimal 8 karakter" required />
        <x-field name="password_confirmation" label="Ulangi password baru" type="password" required />
      </div>
      <div><button class="btn line" type="submit">Ganti password</button></div>
    </form>
  </div>
</div>
@endsection
