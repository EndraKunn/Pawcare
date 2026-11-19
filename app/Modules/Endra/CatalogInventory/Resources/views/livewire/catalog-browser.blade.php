<div>
  <div id="catalogRealtimeBanner" class="alert" style="display:none;margin-bottom:16px">
    🔄 <span id="catalogRealtimeText">Katalog layanan baru saja diperbarui oleh mitra bisnis.</span>
    <button type="button" class="btn sm line" wire:click="$refresh" style="margin-left:auto">Muat ulang</button>
  </div>

  <form id="catalogFilterForm" method="GET" action="{{ route('catalog') }}" wire:submit.prevent class="grid filter-row" style="grid-template-columns:1.6fr 1fr 1fr 1fr auto;align-items:end">
    <label class="fld">
      <span>Cari lokasi, kota, bisnis, atau layanan</span>
      <input
        name="q"
        value="{{ $q }}"
        wire:model.live.debounce.300ms="q"
        placeholder="Contoh: Madiun, Ngawi, atau nama bisnis"
      >
    </label>

    <label class="fld">
      <span>Jenis layanan</span>
      <select name="category" wire:model.live="category">
        <option value="">Semua layanan</option>
        @foreach (\App\Models\Service::CATEGORIES as $c)
          <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
        @endforeach
      </select>
    </label>

    <label class="fld">
      <span>Jenis hewan</span>
      <select name="species" wire:model.live="species">
        <option value="">Semua hewan</option>
        @foreach (\App\Models\Pet::SPECIES as $sp)
          <option value="{{ $sp }}" @selected(($species ?? '') === $sp)>{{ $sp }}</option>
        @endforeach
      </select>
    </label>

    <label class="fld">
      <span>Urutkan</span>
      <select name="sort" wire:model.live="sort">
        <option value="name" @selected(($sort ?? 'name') === 'name')>Nama (A–Z)</option>
        <option value="price_asc" @selected(($sort ?? '') === 'price_asc')>Harga termurah</option>
        <option value="rating_desc" @selected(($sort ?? '') === 'rating_desc')>Rating tertinggi</option>
      </select>
    </label>

    <div class="row" style="gap:8px">
      <button class="btn" style="height:44px" type="submit">Cari</button>
      @if ($q !== '' || ! empty($category) || ! empty($species) || ($sort ?? 'name') !== 'name')
        <button type="button" class="btn line" style="height:44px" wire:click="resetFilters">Reset</button>
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
      <span wire:loading class="pill wait" style="margin-left:8px">Memuat katalog...</span>
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
              <button type="button" class="btn sm line" wire:click="resetFilters">Reset semua filter</button>
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
</div>
