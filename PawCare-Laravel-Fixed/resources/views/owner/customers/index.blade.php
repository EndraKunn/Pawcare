@extends('layouts.dashboard')
@section('title', 'Data Pelanggan')
@section('heading', 'Data pelanggan')
@section('subtitle', 'Pelanggan yang pernah memesan di bisnis Anda.')

@section('content')
<div class="alert" style="margin-bottom:14px">🔒 <span>Data yang ditampilkan dibatasi pada pelanggan yang menggunakan layanan <b>bisnis Anda</b>.</span></div>
<form method="GET" class="row" style="margin-bottom:14px"><label class="fld" style="width:320px"><input name="q" value="{{ $search }}" placeholder="🔎 Cari nama atau telepon"></label><button class="btn sm line" type="submit">Cari</button></form>
@if ($customers->isEmpty())
  <div class="card"><x-empty icon="👥" title="Belum ada pelanggan" /></div>
@else
  <div class="tw"><table><thead><tr><th>Pelanggan</th><th>Telepon</th><th class="num">Booking</th><th class="num">Total belanja</th><th>Terakhir</th><th></th></tr></thead><tbody>
    @foreach ($customers as $c)
      <tr><td><div class="row"><span class="av bl">{{ initials($c->name) }}</span><div><b>{{ $c->name }}</b><br><small class="mut">{{ $c->email }}</small></div></div></td>
        <td class="nw">{{ $c->phone ?? '-' }}</td><td class="num">{{ $c->bookings_here }}</td><td class="num nw">{{ rupiah($c->spent_here) }}</td>
        <td class="nw">{{ $c->last_booking_at ? \Illuminate\Support\Carbon::parse($c->last_booking_at)->translatedFormat('d M Y') : '-' }}</td>
        <td><a class="btn sm line" href="{{ route('owner.customers.show', $c->id) }}">Detail</a></td></tr>
    @endforeach
  </tbody></table></div>
  {{ $customers->links() }}
@endif
@endsection
