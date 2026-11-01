@props(['icon' => '🐾', 'title' => 'Belum ada data', 'text' => null])
<div class="empty"><div class="big">{{ $icon }}</div><p class="b" style="margin:6px 0 2px;color:var(--tx)">{{ $title }}</p>@if($text)<p class="sm" style="margin:0">{{ $text }}</p>@endif {{ $slot }}</div>
