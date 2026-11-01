@extends('layouts.dashboard')
@section('title', 'Chat '.$conversation->customer->name)
@section('heading', 'Chat dengan '.$conversation->customer->name)
@section('subtitle', $conversation->booking ? 'Booking '.$conversation->booking->invoice_number : 'Konsultasi layanan '.$business->business_name)
@section('actions')
  @if ($conversation->booking)
    <a class="btn line" href="{{ route('owner.bookings.show', $conversation->booking_id) }}">📅 Buka booking</a>
  @endif
  <a class="btn line" href="{{ route('owner.chat.index') }}">← Daftar pesan</a>
@endsection

@section('content')
@if ($conversation->booking)
  <div class="card gold" style="margin-bottom:16px">
    <div class="row sp wrapf">
      <div>
        <strong>Konteks Booking: {{ $conversation->booking->invoice_number }}</strong>
        <span class="sm mut" style="margin-left:8px">
          Hewan: {{ $conversation->booking->pet->name ?? '-' }} ({{ $conversation->booking->pet->species ?? '-' }})
          @if ($conversation->booking->items->isNotEmpty())
            · Layanan: {{ $conversation->booking->items->map(fn ($i) => $i->service?->name)->filter()->implode(', ') }}
          @endif
        </span>
      </div>
      <x-status :status="$conversation->booking->status" />
    </div>
  </div>
@endif

<div class="card">
  <div style="max-height:420px;overflow-y:auto;padding-right:6px">
    @forelse ($conversation->messages as $msg)
      @php $isMe = (int) $msg->sender_id === (int) auth()->id(); @endphp
      <div style="display:flex;justify-content:{{ $isMe ? 'flex-end' : 'flex-start' }};margin-bottom:12px">
        <div style="max-width:75%;padding:12px 14px;border-radius:14px;border:1px solid var(--line);background:{{ $isMe ? 'rgba(224,182,74,.14)' : 'var(--field)' }}">
          <div class="row sp" style="gap:12px;margin-bottom:4px">
            <strong class="sm {{ $isMe ? 'gold-t' : '' }}">{{ $isMe ? $business->business_name : ($msg->sender->name ?? 'Pelanggan') }}</strong>
            <small class="mut" style="font-size:11px">
              {{ $msg->created_at?->format('d M Y, H:i') }}
              @if ($isMe)
                · {{ $msg->read_at ? '✓✓ Dibaca' : '✓ Terkirim' }}
              @endif
            </small>
          </div>
          <p style="margin:0;white-space:pre-wrap">{{ $msg->body }}</p>
        </div>
      </div>
    @empty
      <x-empty icon="💬" title="Belum ada pesan" text="Kirim pesan kepada pelanggan melalui formulir di bawah ini." />
    @endforelse
  </div>

  <hr>

  <form method="POST" action="{{ route('owner.chat.send', $conversation->id) }}" class="form">
    @csrf
    <x-field name="body" label="Balas pesan pelanggan" type="textarea" rows="2" placeholder="Tulis balasan untuk pelanggan..." maxlength="2000" required />
    <div class="row" style="justify-content:flex-end">
      <button class="btn" type="submit">Kirim balasan</button>
    </div>
  </form>
</div>
@endsection
