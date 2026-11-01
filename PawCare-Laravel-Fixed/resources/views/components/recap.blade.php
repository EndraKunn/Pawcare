{{-- Rekapitulasi status: $rows = [label => jumlah], $type = booking|payment --}}
@props(['rows', 'type' => 'booking'])
@php $total = max(array_sum($rows), 1); @endphp
@foreach ($rows as $label => $count)
  @php $p = round($count / $total * 100); @endphp
  <div style="margin-bottom:14px">
    <div class="row sp">
      @if ($type === 'payment') <x-pay :status="$label" /> @else <x-status :status="$label" /> @endif
      <span class="sm"><b>{{ $count }}</b> <span class="mut">· {{ $p }}%</span></span>
    </div>
    <div class="hbar" style="margin-top:6px"><i style="width:{{ $p }}%"></i></div>
  </div>
@endforeach
