@extends('layouts.dashboard')
@section('title', 'Detail Pelanggan')
@section('heading', 'Detail pelanggan')
@section('subtitle', $user->name)
@section('actions')<a class="btn line" href="{{ route('owner.customers.index') }}">← Kembali</a>@endsection

@section('content')
<div class="grid g12">
  <div class="card" style="align-self:start"><h3>👤 {{ $user->name }}</h3>
    <dl class="kv mt"><dt>Email</dt><dd>{{ $user->email }}</dd><dt>Telepon</dt><dd>{{ $user->phone ?? '-' }}</dd><dt>Hewan</dt><dd>{{ $pets->map(fn ($p) => $p->emoji.' '.$p->name)->implode(', ') ?: '-' }}</dd></dl>
    <p class="note">Hewan dan riwayat di sini hanya yang berkaitan dengan bisnis Anda.</p>
  </div>
  <div class="card"><h3>Riwayat aktivitas di bisnis Anda</h3>
    @foreach ($bookings as $b)
      <div class="item" style="flex-wrap:wrap"><div><div class="b sm"><a class="gold-t" href="{{ route('owner.bookings.show', $b->id) }}">{{ $b->invoice_number }}</a> · {{ $b->items->map(fn ($i) => $i->service->name.' × '.$i->quantity)->implode(', ') }}</div><small class="mut">{{ $b->schedule_label }} · {{ $b->pet->emoji }} {{ $b->pet->name }}</small></div>
        <div class="row"><x-status :status="$b->status" /><b>{{ rupiah($b->total_price) }}</b></div></div>
    @endforeach
    <p class="note">Total transaksi tidak menghitung booking yang dibatalkan.</p>
  </div>
</div>
@endsection
