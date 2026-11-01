@extends('layouts.dashboard')
@section('title', 'Pantau Layanan')
@section('heading', 'Pantau layanan')
@section('subtitle', 'Seluruh layanan dari semua bisnis (hanya baca).')

@section('content')
<form method="GET" class="row" style="margin-bottom:14px;align-items:flex-end"><label class="fld"><select name="business"><option value="0">Semua bisnis</option>@foreach ($businesses as $b)<option value="{{ $b->id }}" @selected($businessId === $b->id)>{{ $b->business_name }}</option>@endforeach</select></label><button class="btn sm line" type="submit" style="height:44px">Filter</button></form>
@if ($services->isEmpty())<div class="card"><x-empty icon="✂️" title="Tidak ada layanan" /></div>@else
<div class="tw"><table><thead><tr><th>Layanan</th><th>Bisnis</th><th>Jenis</th><th class="num">Harga</th><th>Status</th></tr></thead><tbody>
  @foreach ($services as $s)<tr><td><b>{{ $s->name }}</b></td><td>{{ $s->business->business_name }}</td><td>{{ $s->category }}</td><td class="num nw">{{ rupiah($s->price) }} / {{ $s->unit }}</td><td><x-status :status="$s->status ? 'Aktif' : 'Nonaktif'" /></td></tr>@endforeach
</tbody></table></div>{{ $services->links() }}@endif
@endsection
