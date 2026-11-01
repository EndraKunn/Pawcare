<div class="card media">
  <a class="ph" href="{{ route('catalog.show', $b->id) }}" tabindex="-1" aria-hidden="true">
    <x-photo :src="$b->cover_url" :fallback="$b->cover_fallback_url" :alt="'Foto sampul '.$b->business_name" />
  </a>
  <div class="bd">
    <div class="row sp" style="gap:8px;align-items:flex-start">
      <h3 style="margin:0">{{ $b->business_name }}</h3>
      @if (($b->reviews_count ?? 0) > 0)
        <span class="pill wait" style="font-size:11.5px;padding:2px 9px">★ {{ number_format((float) $b->rating_avg, 1, ',', '.') }} ({{ $b->reviews_count }})</span>
      @else
        <span class="pill gray" style="font-size:11px;padding:2px 8px">Belum ada ulasan</span>
      @endif
    </div>
    <p class="sub" style="margin:4px 0 2px">📍 {{ \Illuminate\Support\Str::limit($b->address ?? '-', 60) }}</p>
    <p class="sm mut" style="margin:0 0 8px">👤 Dikelola oleh {{ $b->owner->name ?? '-' }}</p>
    @if (! empty($b->accepted_species_list))
      <div class="chips" style="gap:6px;margin:0 0 10px">
        @foreach ($b->accepted_species_list as $sp)
          <span class="pill gray" style="font-size:11px;padding:2px 9px">{{ $sp }}</span>
        @endforeach
      </div>
    @endif
    <p class="sm mut" style="margin:0 0 14px">
      @if (! is_null($b->min_price ?? null)) mulai <span class="gold-t b">{{ rupiah($b->min_price) }}</span> @else Belum ada layanan @endif
    </p>
    <a class="btn sm" href="{{ route('catalog.show', $b->id) }}">Lihat layanan</a>
  </div>
</div>
