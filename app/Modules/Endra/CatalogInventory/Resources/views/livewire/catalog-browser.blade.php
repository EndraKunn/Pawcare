<div>
  <div id="catalogRealtimeBanner" class="alert" style="display:none;margin-bottom:16px">
    🔄 <span id="catalogRealtimeText">Katalog layanan baru saja diperbarui oleh mitra bisnis.</span>
    <button type="button" class="btn sm line" wire:click="$refresh" style="margin-left:auto">Muat ulang</button>
  </div>

  <form id="catalogFilterForm" wire:submit.prevent class="grid filter-row" style="grid-template-columns:1.6fr 1fr 1fr 1fr auto;align-items:end">
    <label class="fld">
      <span>Cari lokasi, kota, bisnis, atau layanan</span>
      <input
        type="text"
        name="q"
        wire:model.live.debounce.300ms="q"
        placeholder="Contoh: Madiun, Ngawi, atau nama bisnis"
      >
    </label>

    <label class="fld">
      <span>Jenis layanan</span>
      <select name="category" wire:model.live="category">
        <option value="">Semua layanan</option>
        @foreach (\App\Models\Service::CATEGORIES as $c)
          <option value="{{ $c }}">{{ $c }}</option>
        @endforeach
      </select>
    </label>

    <label class="fld">
      <span>Jenis hewan</span>
      <select name="species" wire:model.live="species">
        <option value="">Semua hewan</option>
        @foreach (\App\Models\Pet::SPECIES as $sp)
          <option value="{{ $sp }}">{{ $sp }}</option>
        @endforeach
      </select>
    </label>

    <label class="fld">
      <span>Urutkan</span>
      <select name="sort" wire:model.live="sort">
        <option value="name">Nama (A–Z)</option>
        <option value="price_asc">Harga termurah</option>
        <option value="rating_desc">Rating tertinggi</option>
      </select>
    </label>

    <div class="row" style="gap:8px">
      <button class="btn" style="height:44px" type="button" wire:click="$refresh">Cari</button>
      @if (trim($q) !== '' || $activeCategory !== '' || $activeSpecies !== '' || $activeSort !== 'name')
        <button type="button" class="btn line" style="height:44px" wire:click="resetFilters">Reset</button>
      @endif
    </div>
  </form>

  <div class="row sp wrapf" style="margin-top:16px">
    <div class="sm mut">
      Menampilkan <b>{{ $businesses->total() }}</b> bisnis mitra
      @if (trim($q) !== '' || $activeCategory !== '' || $activeSpecies !== '')
        <span>untuk filter:</span>
        @if (trim($q) !== '') <span class="pill gray" style="margin-left:4px">📍 "{{ trim($q) }}"</span> @endif
        @if ($activeCategory !== '') <span class="pill gray" style="margin-left:4px">✂️ {{ $activeCategory }}</span> @endif
        @if ($activeSpecies !== '') <span class="pill gray" style="margin-left:4px">🐾 {{ $activeSpecies }}</span> @endif
      @endif
    </div>
    <div wire:loading>
      <span class="pill wait">Memuat katalog...</span>
    </div>
  </div>

  <div class="mt" wire:key="catalog-results-{{ md5($q.'|'.$activeCategory.'|'.$activeSpecies.'|'.$activeSort.'|'.$businesses->currentPage()) }}">
    @if ($businesses->isEmpty())
      <div class="card">
        <x-empty
          icon="🔎"
          title="Tidak ada bisnis yang cocok"
          text="Coba ubah kata kunci lokasi, jenis layanan, atau jenis hewan pada filter pencarian."
        >
          @if (trim($q) !== '' || $activeCategory !== '' || $activeSpecies !== '')
            <div class="mt">
              <button type="button" class="btn sm line" wire:click="resetFilters">Reset semua filter</button>
            </div>
          @endif
        </x-empty>
      </div>
    @else
      <div class="grid g3">
        @foreach ($businesses as $b)
          <div wire:key="biz-card-{{ $b->id }}">
            @include('partials.biz-card', ['b' => $b])
          </div>
        @endforeach
      </div>
      <div class="mt">
        {{ $businesses->links() }}
      </div>
    @endif
  </div>
</div>