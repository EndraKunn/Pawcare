
@props(['status'])

@php
  $cls = match ($status) {
    'Menunggu' => 'wait',
    'Dikonfirmasi' => 'conf',
    'Dalam Proses' => 'prog',
    'Selesai' => 'done',
    'Dibatalkan' => 'bad',
    'Aktif' => 'done',
    'Suspend', 'Nonaktif' => 'bad',
    default => 'gray',
  };
@endphp

<span class="pill {{ $cls }}" data-realtime-status>{{ $status }}</span>