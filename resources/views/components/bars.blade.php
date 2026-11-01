@props(['values', 'labels', 'h' => 170, 'w' => 560, 'decimals' => 0])
@php
  $values = array_values($values);
  $max = max(max($values ?: [0]) * 1.15, 1);
  $n = max(count($values), 1);
  $bw = $w / $n;
  $pad = $bw * .28;
  $gid = 'gg'.substr(md5(uniqid('', true)), 0, 6);
@endphp
<svg viewBox="0 0 {{ $w }} {{ $h }}" width="100%" role="img" aria-label="Grafik batang">
  <defs><linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f0cf7a"/><stop offset="1" stop-color="#c8962a"/></linearGradient></defs>
  @foreach ([0, .5, 1] as $f)
    <line x1="0" x2="{{ $w }}" y1="{{ $h - 24 - ($h - 44) * $f }}" y2="{{ $h - 24 - ($h - 44) * $f }}" stroke="rgba(148,170,214,.14)"/>
  @endforeach
  @foreach ($values as $i => $v)
    @php $bh = ($h - 44) * $v / $max; @endphp
    <rect x="{{ round($i * $bw + $pad, 2) }}" y="{{ round($h - 24 - $bh, 2) }}" width="{{ round($bw - $pad * 2, 2) }}" height="{{ round($bh, 2) }}" rx="7" fill="url(#{{ $gid }})"/>
    <text class="barlbl" x="{{ round($i * $bw + $bw / 2, 2) }}" y="{{ $h - 6 }}" text-anchor="middle">{{ $labels[$i] ?? '' }}</text>
    <text class="barval" x="{{ round($i * $bw + $bw / 2, 2) }}" y="{{ round($h - 28 - $bh, 2) }}" text-anchor="middle">{{ number_format($v, $decimals, ',', '.') }}</text>
  @endforeach
</svg>
