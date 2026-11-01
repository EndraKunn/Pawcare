@extends('layouts.dashboard')
@section('title', 'Booking Masuk')
@section('heading', 'Booking masuk')
@section('subtitle', 'Konfirmasi dan perbarui status sesuai proses layanan.')

@section('content')
<div class="tabs">
  <a href="{{ route('owner.bookings.index') }}" style="text-decoration:none"><button type="button" class="{{ ! $status ? 'on' : '' }}">Semua</button></a>
  @foreach (\App\Models\Booking::STATUSES as $s)
    <a href="{{ route('owner.bookings.index', ['status' => $s]) }}" style="text-decoration:none"><button type="button" class="{{ $status === $s ? 'on' : '' }}">{{ $s }}</button></a>
  @endforeach
</div>
@if ($bookings->isEmpty())
  <div class="card"><x-empty icon="📅" title="Tidak ada booking" /></div>
@else
  <div class="tw"><table><thead><tr><th>Invoice</th><th>Pelanggan</th><th>Hewan</th><th>Layanan</th><th>Jadwal</th><th>Status</th><th>Pembayaran</th><th></th></tr></thead><tbody>
    @foreach ($bookings as $b)
      <tr><td class="nw"><b>{{ $b->invoice_number }}</b></td><td>{{ $b->customer->name }}</td><td>{{ $b->pet->emoji }} {{ $b->pet->name }}</td><td>{{ $b->items->map(fn ($i) => $i->service->name.' × '.$i->quantity)->implode(', ') }}</td><td class="nw">{{ $b->schedule_label }}</td><td><x-status :status="$b->status" /></td><td><x-pay :status="$b->payment->status" /></td><td><a class="btn sm" href="{{ route('owner.bookings.show', $b->id) }}">Kelola</a></td></tr>
    @endforeach
  </tbody></table></div>
  {{ $bookings->links() }}
@endif
@endsection
