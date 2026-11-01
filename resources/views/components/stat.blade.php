@props(['label', 'value', 'note' => null, 'warn' => false])
<div class="stat"><div class="l">{{ $label }}</div><div class="v">{{ $value }}</div>@if($note)<div class="d {{ $warn ? 'w' : '' }}">{{ $note }}</div>@endif</div>
