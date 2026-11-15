@extends('layouts.dashboard')
@section('title', 'Care Board')
@section('heading', 'Care Board')
@section('subtitle', 'Pantau booking aktif dan perkembangan perawatan hewan secara realtime.')

@section('content')
<div class="grid g12">
  <div class="card"><div class="sub">Menunggu</div><div class="b" style="font-size:28px" data-care-stat="waiting">{{ $stats['waiting'] }}</div></div>
  <div class="card"><div class="sub">Dikonfirmasi</div><div class="b" style="font-size:28px" data-care-stat="confirmed">{{ $stats['confirmed'] }}</div></div>
  <div class="card"><div class="sub">Dalam proses</div><div class="b" style="font-size:28px" data-care-stat="progress">{{ $stats['progress'] }}</div></div>
</div>
<div class="row sp wrapf mt">
  <div><h3 style="margin:0">Papan perawatan</h3><p class="sub">Perubahan status akan muncul tanpa memuat ulang halaman.</p></div>
  <a class="btn line" href="{{ route('owner.bookings.index') }}">Semua booking</a>
</div>
<div class="grid g12 mt" style="align-items:start">
@foreach ($columns as $status => $items)
  <section class="card" data-care-column="{{ $status }}">
    <div class="row sp"><strong>{{ $status }}</strong><span class="pill gray" data-care-count>{{ $items->count() }}</span></div>
    <div data-care-items>
    @forelse ($items as $booking)
      <article class="item" data-realtime-booking-id="{{ $booking->id }}" style="display:block;margin-top:12px">
        <div class="row sp"><strong>{{ $booking->invoice_number }}</strong><span class="pill gray" data-realtime-status>{{ $booking->status }}</span></div>
        <p class="sm" style="margin:8px 0 2px"><b>{{ $booking->pet->name }}</b> · {{ $booking->pet->species }}</p>
        <p class="sm mut" style="margin:0">{{ $booking->customer->name }}</p>
        <p class="sm mut" style="margin:4px 0">{{ $booking->schedule_label }}</p>
        <p class="sm" style="margin:4px 0">{{ $booking->items->map(fn ($item) => $item->service?->name)->filter()->implode(', ') }}</p>
        <div class="row sp mt"><span class="sm mut">{{ $booking->careNotes->count() }} catatan perawatan</span><a class="btn sm" href="{{ route('owner.bookings.show', $booking->id) }}">Kelola</a></div>
      </article>
    @empty
      <p class="sm mut" data-care-empty style="margin:14px 0 0">Belum ada booking pada tahap ini.</p>
    @endforelse
    </div>
  </section>
@endforeach
</div>
@endsection
