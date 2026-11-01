@extends('layouts.dashboard')
@section('title', 'Pesan Pelanggan')
@section('heading', 'Pesan pelanggan')
@section('subtitle', 'Percakapan pelanggan yang menghubungi '.$business->business_name.'.')

@section('content')
<div class="card">
  @forelse ($conversations as $conv)
    <div class="item {{ ($conv->unread_count ?? 0) > 0 ? 'sel' : '' }}" style="padding:14px 10px">
      <div style="min-width:0;flex:1">
        <div class="row sp wrapf" style="gap:8px">
          <div>
            <strong>{{ $conv->customer->name ?? 'Pelanggan' }}</strong>
            @if ($conv->booking)
              <span class="pill gray" style="margin-left:6px;font-size:11.5px">{{ $conv->booking->invoice_number }} ({{ $conv->booking->status }})</span>
            @endif
            @if (($conv->unread_count ?? 0) > 0)
              <span class="pill wait" style="margin-left:6px;font-size:11.5px">{{ $conv->unread_count }} belum dibaca</span>
            @endif
          </div>
          <small class="mut">{{ $conv->last_message_at?->translatedFormat('d M Y, H:i') ?? $conv->created_at?->translatedFormat('d M Y, H:i') }}</small>
        </div>

        <p class="sub" style="margin:6px 0 0">
          {{ $conv->latestMessage ? \Illuminate\Support\Str::limit($conv->latestMessage->body, 90) : 'Belum ada pesan.' }}
        </p>
      </div>

      <a class="btn sm {{ ($conv->unread_count ?? 0) > 0 ? '' : 'line' }}" href="{{ route('owner.chat.show', $conv->id) }}">Balas</a>
    </div>
  @empty
    <x-empty icon="💬" title="Belum ada percakapan masuk" text="Pesan dari pelanggan terkait layanan atau booking bisnis Anda akan tampil di sini." />
  @endforelse

  <div class="mt">{{ $conversations->links() }}</div>
</div>
@endsection
