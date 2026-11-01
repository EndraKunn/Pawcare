@extends('layouts.dashboard')
@section('title', 'Detail Pengguna')
@section('heading', 'Detail pengguna')
@section('subtitle', $user->name)
@section('actions')<a class="btn line" href="{{ route('admin.users.index') }}">← Kembali</a>@endsection

@section('content')
<div class="grid g2">
  <div class="card"><div class="row sp"><h3>{{ $user->name }}</h3><x-status :status="$user->status ? 'Aktif' : 'Nonaktif'" /></div>
    <dl class="kv mt"><dt>Email</dt><dd>{{ $user->email }}</dd><dt>Telepon</dt><dd>{{ $user->phone ?? '-' }}</dd><dt>Role</dt><dd><span class="rolecode">{{ $user->role }}</span></dd><dt>Terdaftar</dt><dd>{{ $user->created_at->translatedFormat('d M Y, H.i') }}</dd>
      @if ($user->isCustomer())<dt>Hewan</dt><dd>{{ $user->pets_count }}</dd><dt>Booking</dt><dd>{{ $user->bookings_count }}</dd>@endif
      @if ($user->isOwner())<dt>Bisnis</dt><dd>{{ $user->businesses_count }}</dd>@endif</dl>
  </div>
  <div class="card">
    @if ($user->isOwner())
      <h3>Bisnis milik pengguna</h3>
      @foreach ($businesses as $b)<div class="item"><div><a class="gold-t b" href="{{ route('admin.clients.show', $b->id) }}">{{ $b->business_name }}</a><br><small class="mut">{{ $b->services_count }} layanan · {{ $b->bookings_count }} booking</small></div><x-status :status="$b->status ? 'Aktif' : 'Suspend'" /></div>@endforeach
    @elseif ($user->isCustomer())
      <h3>Booking terbaru</h3>
      @forelse ($recentBookings as $b)<div class="item"><div><b class="sm">{{ $b->invoice_number }}</b><br><small class="mut">{{ $b->business->business_name }} · {{ $b->pet->name }}</small></div><x-status :status="$b->status" /></div>@empty<x-empty icon="📅" title="Belum ada booking" />@endforelse
    @else
      <h3>Administrator</h3><p class="sub">Akun admin dibuat melalui seeder, bukan registrasi publik.</p>
    @endif
  </div>
</div>
@endsection
