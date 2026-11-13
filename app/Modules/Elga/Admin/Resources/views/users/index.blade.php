@extends('layouts.dashboard')
@section('title', 'Kelola Pengguna')
@section('heading', 'Kelola pengguna')
@section('subtitle', 'Seluruh akun pada platform PawCare.')

@section('content')
<div class="tabs">
  <a href="{{ route('admin.users.index') }}" style="text-decoration:none"><button type="button" class="{{ ! $role ? 'on' : '' }}">Semua ({{ $counts['all'] }})</button></a>
  @foreach (['customer' => 'Customer', 'business_owner' => 'Pemilik bisnis', 'admin' => 'Admin'] as $k => $label)
    <a href="{{ route('admin.users.index', ['role' => $k]) }}" style="text-decoration:none"><button type="button" class="{{ $role === $k ? 'on' : '' }}">{{ $label }} ({{ $counts[$k] }})</button></a>
  @endforeach
</div>
<form method="GET" class="row" style="margin-bottom:14px">@if($role)<input type="hidden" name="role" value="{{ $role }}">@endif<label class="fld" style="width:320px"><input name="q" value="{{ $q }}" placeholder="🔎 Cari nama atau email"></label><button class="btn sm line" type="submit">Cari</button></form>
<div class="tw"><table><thead><tr><th>Pengguna</th><th>Telepon</th><th>Role</th><th>Status</th><th>Terdaftar</th><th></th></tr></thead><tbody>
  @foreach ($users as $u)
    <tr><td><div class="row"><span class="av {{ $u->isCustomer() ? 'bl' : '' }}">{{ initials($u->name) }}</span><div><b>{{ $u->name }}</b><br><small class="mut">{{ $u->email }}</small></div></div></td>
      <td class="nw">{{ $u->phone ?? '-' }}</td><td><span class="rolecode">{{ $u->role }}</span></td><td><x-status :status="$u->status ? 'Aktif' : 'Nonaktif'" /></td><td class="nw">{{ $u->created_at->translatedFormat('d M Y') }}</td>
      <td><div class="acts"><a class="btn sm line" href="{{ route('admin.users.show', $u->id) }}">Detail</a>
        @if ($u->id !== auth()->id())
          <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" data-confirm="{{ $u->status ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $u->name }}?">@csrf @method('PATCH')<button class="btn sm {{ $u->status ? 'danger' : 'ok' }}" type="submit">{{ $u->status ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
        @endif</div></td></tr>
  @endforeach
</tbody></table></div>
{{ $users->links() }}
@endsection
