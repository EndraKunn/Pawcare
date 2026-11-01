@extends('layouts.dashboard')
@section('title', 'Manajemen Klien')
@section('heading', 'Manajemen klien')
@section('subtitle', 'Klien = pemilik bisnis (role business_owner) beserta bisnis miliknya.')

@section('content')
<div class="sm mut" style="margin-bottom:10px">{{ $businesses->total() }} bisnis ditemukan · {{ $active }} aktif · {{ $suspended }} disuspend</div>
<form method="GET" class="row wrapf" style="margin-bottom:14px;align-items:flex-end">
  <label class="fld" style="width:300px"><input name="q" value="{{ $q }}" placeholder="🔎 Cari bisnis, pemilik, email"></label>
  <label class="fld"><select name="status"><option value="">Semua status</option><option value="aktif" @selected($status === 'aktif')>Aktif</option><option value="suspend" @selected($status === 'suspend')>Suspend</option></select></label>
  <button class="btn sm line" type="submit" style="height:44px">Filter</button>
</form>
@if ($businesses->isEmpty())
  <div class="card"><x-empty icon="🏢" title="Tidak ada bisnis" /></div>
@else
<div class="tw"><table><thead><tr><th>Bisnis</th><th>Pemilik</th><th>Telepon</th><th>Alamat</th><th class="num">Layanan</th><th class="num">Booking</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
  @foreach ($businesses as $b)
    <tr><td><div class="row"><span style="font-size:22px">{{ $b->icon }}</span><b>{{ $b->business_name }}</b></div></td>
      <td><b>{{ $b->owner->name }}</b><br><small class="mut">{{ $b->owner->email }}</small></td><td class="nw">{{ $b->owner->phone ?? '-' }}</td>
      <td>{{ \Illuminate\Support\Str::limit($b->address ?? '-', 40) }}</td><td class="num">{{ $b->services_count }}</td><td class="num">{{ $b->bookings_count }}</td>
      <td><x-status :status="$b->status ? 'Aktif' : 'Suspend'" /></td>
      <td><div class="acts" style="flex-wrap:wrap;min-width:230px">
        <a class="btn sm line" href="{{ route('admin.clients.show', $b->id) }}">Detail</a>
        <a class="btn sm line" href="{{ route('admin.clients.edit', $b->id) }}">Ubah</a>
        <form method="POST" action="{{ route('admin.clients.toggle', $b->id) }}" data-confirm="{{ $b->status ? 'Suspend' : 'Aktifkan' }} bisnis {{ $b->business_name }}?">@csrf @method('PATCH')<button class="btn sm {{ $b->status ? 'danger' : 'ok' }}" type="submit">{{ $b->status ? 'Suspend' : 'Aktifkan' }}</button></form>
      </div></td></tr>
  @endforeach
</tbody></table></div>
{{ $businesses->links() }}
@endif
<p class="note">Bisnis yang disuspend tidak tampil di katalog dan tidak menerima booking baru.</p>
@endsection
