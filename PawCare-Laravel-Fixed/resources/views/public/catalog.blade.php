@extends('layouts.public')
@section('title', 'Katalog Bisnis')

@section('content')
<h2 style="margin:0 0 4px;font-size:26px">Temukan mitra terbaik</h2>
<p class="mut" style="margin:0 0 20px">Bandingkan bisnis penitipan dan perawatan hewan. Setiap bisnis memiliki layanan, jadwal, dan harga sendiri.</p>

<form method="GET" action="{{ route('catalog') }}" class="grid filter-row" style="grid-template-columns:1.8fr 1fr 1fr auto;align-items:end">
  <label class="fld">
    <span>Cari lokasi, kota, atau bisnis</span>
    <input name="q" value="{{ $q }}" placeholder="Contoh: Madiun, Ngawi, atau nama bisnis">
  </label>

  <label class="fld">
    <span>Jenis layanan</span>
    <select name="category">
      <option value="">Semua layanan</option>
      @foreach (\App\Models\Service::CATEGORIES as $c)
        <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
      @endforeach
    </select>
  </label>

  <label class="fld">
    <span>Jenis hewan</span>
    <select name="species">
      <option value="">Semua hewan</option>
      @foreach (\App\Models\Pet::SPECIES as $sp)
        <option value="{{ $sp }}" @selected(($species ?? '') === $sp)>{{ $sp }}</option>
      @endforeach
    </select>
  </label>

  <div class="row" style="gap:8px">
    <button class="btn" style="height:44px" type="submit">Cari</button>
    @if ($q !== '' || ! empty($category) || ! empty($species))
      <a class="btn line" style="height:44px" href="{{ route('catalog') }}">Reset</a>
    @endif
  </div>
</form>

<div class="row sp wrapf" style="margin-top:16px">
  <div class="sm mut">
    Menampilkan <b>{{ $businesses->total() }}</b> bisnis mitra
    @if ($q !== '' || ! empty($category) || ! empty($species))
      untuk filter:
      @if ($q !== '') <span class="pill gray" style="margin-left:4px">📍 "{{ $q }}"</span> @endif
      @if (! empty($category)) <span class="pill gray" style="margin-left:4px">✂️ {{ $category }}</span> @endif
      @if (! empty($species)) <span class="pill gray" style="margin-left:4px">🐾 {{ $species }}</span> @endif
    @endif
  </div>
</div>

<div class="mt">
  @if ($businesses->isEmpty())
    <div class="card">
      <x-empty
        icon="🔎"
        title="Tidak ada bisnis yang cocok"
        text="Coba ubah kata kunci lokasi, jenis layanan, atau jenis hewan pada filter pencarian."
      >
        @if ($q !== '' || ! empty($category) || ! empty($species))
          <div class="mt">
            <a class="btn sm line" href="{{ route('catalog') }}">Reset semua filter</a>
          </div>
        @endif
      </x-empty>
    </div>
  @else
    <div class="grid g3">
      @foreach ($businesses as $b)
        @include('partials.biz-card', ['b' => $b])
      @endforeach
    </div>
    {{ $businesses->links() }}
  @endif
</div>
@endsection
