@extends('layouts.dashboard')
@section('title', $business->business_name)
@section('heading', $business->business_name)
@section('subtitle', 'Terdaftar '.$business->created_at->translatedFormat('d M Y'))
@section('actions')<a class="btn line" href="{{ route('admin.clients.index') }}">← Kembali</a><a class="btn" href="{{ route('admin.clients.edit', $business->id) }}">Ubah informasi</a>@endsection

@section('content')
<div class="card"><div class="row sp wrapf"><div class="row"><div class="ico" style="margin:0">{{ $business->icon }}</div><div><h3>{{ $business->business_name }}</h3><p class="sub" style="margin:0">Pemilik: {{ $business->owner->name }}</p></div></div><x-status :status="$business->status ? 'Aktif' : 'Suspend'" /></div><hr>
  <div class="grid g2">
    <dl class="kv"><dt>Pemilik</dt><dd>{{ $business->owner->name }}</dd><dt>Email</dt><dd>{{ $business->owner->email }}</dd><dt>Telepon</dt><dd>{{ $business->owner->phone ?? '-' }}</dd><dt>Alamat</dt><dd>{{ $business->address ?? '-' }}</dd></dl>
    <dl class="kv"><dt>Layanan</dt><dd>{{ $business->services_count }}</dd><dt>Booking</dt><dd>{{ $business->bookings_count }}</dd><dt>Pembayaran berhasil</dt><dd>{{ $paidCount }}</dd><dt>Total transaksi</dt><dd class="b gold-t">{{ rupiah($revenue) }}</dd></dl>
  </div><hr>
  <form method="POST" action="{{ route('admin.clients.toggle', $business->id) }}" data-confirm="Ubah status bisnis ini?">@csrf @method('PATCH')<button class="btn sm {{ $business->status ? 'danger' : 'ok' }}" type="submit">{{ $business->status ? 'Suspend bisnis' : 'Aktifkan bisnis' }}</button></form>
</div>
<div class="sec-t">Layanan bisnis</div>
@if ($services->isEmpty())<div class="card"><x-empty icon="✂️" title="Belum ada layanan" /></div>@else
<div class="tw"><table><thead><tr><th>Layanan</th><th>Jenis</th><th class="num">Harga</th><th>Status</th></tr></thead><tbody>
  @foreach ($services as $s)<tr><td><b>{{ $s->name }}</b></td><td>{{ $s->category }}</td><td class="num nw">{{ rupiah($s->price) }} / {{ $s->unit }}</td><td><x-status :status="$s->status ? 'Aktif' : 'Nonaktif'" /></td></tr>@endforeach
</tbody></table></div>@endif
@endsection
