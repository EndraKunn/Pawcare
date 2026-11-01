@extends('layouts.dashboard')
@section('title', 'Chat '.$conversation->business->business_name)
@section('heading', 'Chat dengan '.$conversation->business->business_name)
@section('subtitle', $conversation->booking ? 'Terkait booking '.$conversation->booking->invoice_number : 'Percakapan umum layanan')
@section('actions')
  @if ($conversation->booking)
    <a class="btn line" href="{{ route('customer.bookings.show', $conversation->booking_id) }}">📅 Detail booking</a>
  @endif
  <a class="btn line" href="{{ route('customer.chat.index') }}">← Daftar pesan</a>
@endsection

@section('content')
@if ($conversation->booking)
  <div class="card gold" style="margin-bottom:16px">
    <div class="row sp wrapf">
      <div>
        <strong>Konteks Booking: {{ $conversation->booking->invoice_number }}</strong>
        <span class="sm mut" style="margin-left:8px">
          Hewan: {{ $conversation->booking->pet->name ?? '-' }} ({{ $conversation->booking->pet->species ?? '-' }})
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
            <strong class="sm {{ $isMe ? 'gold-t' : '' }}">{{ $isMe ? 'Anda' : ($msg->sender->name ?? $conversation->business->business_name) }}</strong>
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
      <x-empty icon="💬" title="Percakapan dimulai" text="Tulis pesan pertama Anda kepada bisnis di formulir bawah." />
    @endforelse
  </div>

  <hr>

  <form method="POST" action="{{ route('customer.chat.send', $conversation->id) }}" class="form">
    @csrf
    <x-field name="body" label="Tulis pesan" type="textarea" rows="2" placeholder="Tanyakan ketersediaan layanan, kebutuhan hewan, atau detail booking..." maxlength="2000" required />
    <div class="row" style="justify-content:flex-end">
      <button class="btn" type="submit">Kirim pesan</button>
    </div>
  </form>
</div>
@endsection
