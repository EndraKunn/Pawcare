@props(['src', 'fallback', 'alt' => '', 'eager' => false])
{{-- Gambar dengan fallback otomatis: jika $src gagal dimuat, browser memakai $fallback. --}}
<img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes }} @unless ($eager) loading="lazy" @endunless decoding="async" onerror="this.onerror=null;this.src='{{ $fallback }}'">
