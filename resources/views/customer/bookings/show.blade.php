@extends('layouts.dashboard')
@section('title', $booking->invoice_number)
@section('heading', $booking->invoice_number)
@section('subtitle', $booking->business->business_name.' · '.$booking->pet->name)
@section('actions')
  <form method="POST" action="{{ route('customer.chat.start') }}" class="inline">
    @csrf
    <input type="hidden" name="business_id" value="{{ $booking->business_id }}">
    <input type="hidden" name="booking_id" value="{{ $booking->id }}">
    <button class="btn line" type="submit">💬 Chat bisnis</button>
  </form>
  @if ($booking->status === 'Menunggu')
    <form method="POST" action="{{ route('customer.bookings.cancel', $booking->id) }}" data-confirm="Batalkan booking ini?">@csrf @method('PATCH')<button class="btn danger" type="submit">Batalkan booking</button></form>
  @endif
  <a class="btn line" href="{{ route('customer.bookings.index') }}">← Kembali</a>
@endsection

@section('content')
<div class="grid g12">
  <div>
    <div class="card"><h3>Status layanan</h3><p class="sub">Diperbarui oleh bisnis.</p>
      <ul class="tl">
        @foreach ($booking->statusLogs as $log)
          <li class="{{ $loop->last ? 'now' : 'ok' }}"><span class="dot">{{ $loop->last ? '●' : '✓' }}</span><b>{{ $log->status }}</b>
            <small>{{ $log->changed_at->translatedFormat('d M Y, H.i') }} · oleh {{ $log->changer->name }}@if($log->note) · {{ $log->note }}@endif</small></li>
        @endforeach
      </ul>
    </div>

    {{-- CATATAN HASIL PERAWATAN DARI BISNIS (FITUR 6) --}}
    @if ($booking->careNotes && $booking->careNotes->isNotEmpty())
      <div class="card mt">
        <h3>🩺 Catatan hasil perawatan bisnis</h3>
        <p class="sub">Dicatat oleh tim {{ $booking->business->business_name }}.</p>
        @foreach ($booking->careNotes as $cn)
          <div class="item" style="flex-direction:column;align-items:stretch;gap:4px">
            <div class="row sp">
              <strong>Oleh {{ $cn->author?->name ?? $booking->business->business_name }}</strong>
              <small class="mut">{{ $cn->created_at?->translatedFormat('d M Y, H:i') }}</small>
            </div>
            <p style="margin:4px 0">{{ $cn->care_summary }}</p>
            @if ($cn->recommendations)
              <small class="gold-t">Saran perawatan lanjutan: {{ $cn->recommendations }}</small>
            @endif
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div>
    <div class="card"><div class="row sp"><h3>Ringkasan</h3><x-status :status="$booking->status" /></div>
      <dl class="kv mt">
        <dt>Bisnis</dt><dd>{{ $booking->business->business_name }}</dd>
        <dt>Hewan</dt><dd><a class="gold-t" href="{{ route('customer.pets.health.show', $booking->pet_id) }}">{{ $booking->pet->emoji }} {{ $booking->pet->name }}</a></dd>
        <dt>Jadwal</dt><dd>{{ $booking->schedule_label }} ({{ $booking->time_label }}–{{ $booking->end_time_label }})</dd>
        <dt>Catatan</dt><dd>{{ $booking->notes ?? '-' }}</dd>
      </dl>
      <hr>
      <div class="tw"><table><thead><tr><th>Layanan</th><th class="num">Harga</th><th class="num">Jml</th><th class="num">Subtotal</th></tr></thead><tbody>
        @foreach ($booking->items as $i)
          <tr><td>{{ $i->service->name }}</td><td class="num">{{ rupiah($i->price) }}</td><td class="num">{{ $i->quantity }}</td><td class="num">{{ rupiah($i->subtotal) }}</td></tr>
        @endforeach
      </tbody></table></div>
      <div class="total"><span>Total</span><b>{{ rupiah($booking->total_price) }}</b></div>
    </div>

    <div class="card gold mt row sp"><div><h3>Pembayaran</h3><div><x-pay :status="$booking->payment->status" /></div>
        @if ($booking->payment->gateway_status)
          <p class="sm mut" style="margin:6px 0 0">Gateway: {{ $booking->payment->gateway_status_label }} ({{ $booking->payment->gateway_reference }})</p>
        @endif
        @if ($booking->payment->status === 'Ditolak' && $booking->payment->reject_reason)<p class="sm" style="margin:8px 0 0;color:var(--bad)">Alasan penolakan: {{ $booking->payment->reject_reason }}</p>@endif</div>
      @if ($booking->status !== 'Dibatalkan' && in_array($booking->payment->status, ['Belum Dibayar', 'Ditolak']))<a class="btn sm" href="{{ route('customer.bookings.pay', $booking->id) }}">{{ $booking->payment->status === 'Ditolak' ? 'Unggah ulang / Bayar' : 'Bayar sekarang' }}</a>@endif
    </div>

    {{-- RATING & ULASAN PELANGGAN (FITUR 1) --}}
    <div class="card mt">
      <h3>⭐ Rating &amp; Ulasan Layanan</h3>
      @if ($booking->review)
        @php $rev = $booking->review; @endphp
        <p class="sub">Ulasan Anda untuk booking ini ({{ $rev->created_at?->translatedFormat('d M Y, H:i') }}).</p>
        <div style="padding:12px;border:1px solid var(--line);border-radius:12px;background:var(--field)">
          <div class="gold-t b" style="font-size:16px">{{ $rev->stars_label }} ({{ $rev->rating }}/5)</div>
          <p style="margin:6px 0 0">{{ $rev->comment ?: 'Tanpa komentar tertulis.' }}</p>
        </div>

        @if ($rev->reply)
          <div style="margin-top:10px;padding:10px 14px;border-left:3px solid var(--gold);background:rgba(224,182,74,.08);border-radius:8px">
            <small class="gold-t b" style="display:block">Tanggapan {{ $booking->business->business_name }}</small>
            <p class="sm" style="margin:4px 0 0">{{ $rev->reply->reply }}</p>
          </div>
        @endif

        <details class="mt">
          <summary class="sm gold-t" style="cursor:pointer">Ubah atau hapus ulasan</summary>
          <form method="POST" action="{{ route('customer.reviews.update', $rev->id) }}" class="form mt">
            @csrf @method('PUT')
            <x-field name="rating" label="Rating bintang (1–5)" type="select" :options="[5 => '5 ★ — Sangat Puas', 4 => '4 ★ — Puas', 3 => '3 ★ — Cukup', 2 => '2 ★ — Kurang', 1 => '1 ★ — Tidak Puas']" :value="$rev->rating" required />
            <x-field name="comment" label="Ulasan tertulis (maks. 1.000 karakter)" type="textarea" :value="$rev->comment" maxlength="1000" />
            <div class="row" style="justify-content:flex-end">
              <button class="btn sm" type="submit">Simpan perubahan ulasan</button>
            </div>
          </form>
          <form method="POST" action="{{ route('customer.reviews.destroy', $rev->id) }}" class="mt" onsubmit="return confirm('Hapus ulasan ini?')">
            @csrf @method('DELETE')
            <button class="btn sm danger" type="submit">Hapus ulasan</button>
          </form>
        </details>
      @elseif ($booking->status === \App\Models\Booking::STATUS_DONE)
        <p class="sub">Layanan telah selesai. Bagikan penilaian Anda untuk membantu pelanggan lain.</p>
        <form method="POST" action="{{ route('customer.reviews.store', $booking->id) }}" class="form">
          @csrf
          <x-field name="rating" label="Pilih rating bintang" type="select" :options="[5 => '5 ★ — Sangat Puas', 4 => '4 ★ — Puas', 3 => '3 ★ — Cukup', 2 => '2 ★ — Kurang', 1 => '1 ★ — Tidak Puas']" value="5" required />
          <x-field name="comment" label="Ulasan tertulis (opsional, maks. 1.000 karakter)" type="textarea" placeholder="Ceritakan pengalaman hewan kesayangan Anda di bisnis ini" maxlength="1000" />
          <div class="row" style="justify-content:flex-end">
            <button class="btn" type="submit">Kirim ulasan</button>
          </div>
        </form>
      @else
        <p class="sub" style="margin:4px 0 0">Rating dan ulasan dapat diberikan setelah status booking berubah menjadi <b>Selesai</b>.</p>
      @endif
    </div>
  </div>
</div>
@endsection
