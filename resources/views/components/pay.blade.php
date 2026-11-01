@props(['status'])
@php
  $cls = match ($status) {
    'Menunggu Verifikasi' => 'wait pay', 'Dibayar' => 'done pay', 'Ditolak' => 'bad pay', default => 'gray pay',
  };
@endphp
<span class="pill {{ $cls }}">💳 {{ $status }}</span>
