@extends('layouts.base')

@section('body')
<div class="frame"><div class="scr">
  <div class="nav">
    <a class="lg" href="{{ route('home') }}" style="text-decoration:none;color:inherit">🐾 Paw<b>Care</b></a>
    <div class="lk">
      <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'on' : '' }}">Beranda</a>
      <a href="{{ route('catalog') }}" class="{{ request()->routeIs('catalog*') ? 'on' : '' }}">Layanan</a>
      <a href="{{ route('register.owner') }}" class="{{ request()->routeIs('register.owner') ? 'on' : '' }}">Mitra</a>
    </div>
    <div class="rt">
      @auth
        <a href="{{ route('dashboard') }}" class="gold-t" style="text-decoration:none">Dashboard</a>
        <form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button class="btn sm line" type="submit">Keluar</button></form>
      @else
        <a href="{{ route('login') }}" style="color:inherit;text-decoration:none">Masuk</a>
        <a class="btn sm" href="{{ route('register.customer') }}">Daftar</a>
      @endauth
    </div>
  </div>
  @hasSection('bare')
    @yield('bare')
  @else
    <div class="pad" style="padding-top:28px">
      @include('partials.flash')
      @yield('content')
    </div>
  @endif
  <div class="foot"><span>🐾 PawCare · Sistem informasi layanan penitipan &amp; perawatan hewan</span><span>halo@pawcare.test</span></div>
</div></div>
@endsection
