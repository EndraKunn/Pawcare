@extends('layouts.dashboard')
@section('title', 'Dashboard')
@section('heading', 'Halo, '.explode(' ', $user->name)[0].' 👋')
@section('subtitle', 'Ringkasan booking dan hewan kesayangan Anda.')

@section('content')
@if ($firstUnpaid)
  <div class="alert" style="margin-bottom:18px">💳 <span>Booking <b>{{ $firstUnpaid->booking->invoice_number }}</b> {{ $firstUnpaid->status === 'Ditolak' ? 'bukti pembayarannya ditolak.' : 'belum dibayar.' }} Kirim bukti pembayaran agar bisnis dapat memverifikasinya.</span>
    <span style="margin-left:auto"><a class="btn sm" href="{{ route('customer.bookings.pay', $firstUnpaid->booking_id) }}">Bayar sekarang</a></span></div>
@endif
<div class="grid g4">
  <x-stat label="Booking aktif" :value="$active" :note="$inProgress.' sedang diproses'" />
  <x-stat label="Perlu dibayar" :value="$needPayCount" :note="rupiah($needPayTotal)" :warn="true" />
  <x-stat label="Hewan terdaftar" :value="$petsCount" :note="$petNames ?: 'Belum ada'" />
  <x-stat label="Total transaksi" :value="rupiah($paidTotal)" :note="$paidCount.' pembayaran'" />
</div>
<div class="grid g21 mt2">
  <div>
    <div class="sec-t" style="margin-top:0">Booking terbaru</div>
    @if ($recent->isEmpty())
      <div class="card"><x-empty icon="📅" title="Belum ada booking" text="Pilih bisnis dan buat booking pertama Anda."><p><a class="btn sm" href="{{ route('catalog') }}">Cari layanan</a></p></x-empty></div>
    @else
      <div class="tw"><table><thead><tr><th>Invoice</th><th>Bisnis</th><th>Hewan</th><th>Status</th></tr></thead><tbody>
        @foreach ($recent as $b)
          <tr><td><a class="gold-t" href="{{ route('customer.bookings.show', $b->id) }}">{{ $b->invoice_number }}</a></td><td>{{ $b->business->business_name }}</td><td>{{ $b->pet->name }}</td><td><x-status :status="$b->status" /></td></tr>
        @endforeach
      </tbody></table></div>
    @endif
  </div>
  <div>
    <div class="sec-t" style="margin-top:0">Hewan saya</div>
    <div class="card">
      @forelse ($pets as $p)
        <div class="item"><div class="row"><x-photo class="thumb round" :src="$p->photo_url" :fallback="$p->photo_fallback_url" :alt="$p->name" /><div><div class="b">{{ $p->name }}</div><small class="mut">{{ $p->breed ?? $p->species }}</small></div></div><span class="pill gray">{{ $p->species }}</span></div>
      @empty
        <x-empty icon="🐾" title="Belum ada hewan" />
      @endforelse
      <div class="mt"><a class="btn line sm" href="{{ route('customer.pets.create') }}">+ Tambah hewan</a></div>
    </div>
  </div>
</div>
@endsection
