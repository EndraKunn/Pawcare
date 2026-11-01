@extends('layouts.dashboard')
@section('title', $booking->invoice_number)
@section('heading', $booking->invoice_number)
@section('subtitle', $booking->customer->name.' · '.$booking->pet->name)
@section('actions')
  <form method="POST" action="{{ route('owner.chat.from-booking', $booking->id) }}" class="inline">
    @csrf
    <button class="btn line" type="submit">💬 Chat pelanggan</button>
  </form>
  <a class="btn line" href="{{ route('owner.bookings.index') }}">← Kembali</a>
@endsection

@section('content')
@php $next = $booking->allowedNextStatuses(); @endphp
<div class="grid g21">
  <div>
    <div class="card gold"><div class="row sp"><h3>{{ $booking->invoice_number }}</h3><x-status :status="$booking->status" /></div>
      <p class="sub">{{ $booking->customer->name }} · {{ $booking->pet->emoji }} {{ $booking->pet->name }} ({{ $booking->pet->species }})</p>
      <dl class="kv"><dt>Jadwal</dt><dd>{{ $booking->schedule_label }} ({{ $booking->time_label }}–{{ $booking->end_time_label }})</dd><dt>Telepon</dt><dd>{{ $booking->customer->phone ?? '-' }}</dd><dt>Catatan</dt><dd>{{ $booking->notes ?? '-' }}</dd></dl>
      <hr>
      <div class="tw"><table><thead><tr><th>Layanan</th><th class="num">Harga</th><th class="num">Jml</th><th class="num">Subtotal</th></tr></thead><tbody>
        @foreach ($booking->items as $i)<tr><td>{{ $i->service->name }}</td><td class="num">{{ rupiah($i->price) }}</td><td class="num">{{ $i->quantity }}</td><td class="num">{{ rupiah($i->subtotal) }}</td></tr>@endforeach
      </tbody></table></div>
      <div class="total"><span>Total</span><b>{{ rupiah($booking->total_price) }}</b></div>
      <hr>
      @if ($next)
        <form method="POST" action="{{ route('owner.bookings.status', $booking->id) }}">
          @csrf @method('PATCH')
          <div class="fld"><span>Ubah status</span>
            <div class="chips" style="gap:8px">
              @foreach ($next as $s)
                <button class="btn sm {{ $s === 'Dibatalkan' ? 'danger' : '' }}" type="submit" name="status" value="{{ $s }}">{{ $s === 'Dikonfirmasi' ? '✓ ' : '' }}{{ $s }}</button>
              @endforeach
            </div>
          </div>
          <div class="mt"><x-field name="note" label="Catatan status (opsional)" type="textarea" placeholder="Contoh: kandang nomor 4 sudah disiapkan" /></div>
        </form>
      @else
        <p class="sm mut" style="margin:0">Booking berstatus final, status tidak dapat diubah lagi.</p>
      @endif
      @error('status')<p class="err-t">{{ $message }}</p>@enderror
    </div>

    <div class="card mt row sp"><div><h3>Pembayaran</h3><div><x-pay :status="$booking->payment->status" /></div></div>
      <a class="btn sm line" href="{{ route('owner.payments.show', $booking->payment->id) }}">Buka pembayaran</a></div>

    {{-- INFORMASI KESEHATAN HEWAN UNTUK LAYANAN (FITUR 6) --}}
    <div class="card mt">
      <h3>🩺 Informasi kesehatan hewan ({{ $booking->pet->name }})</h3>
      @if (! $booking->pet->share_health_with_business)
        <p class="sub" style="margin:4px 0 0">Pelanggan membatasi akses ringkasan kesehatan pribadi hewan ini.</p>
      @elseif ($booking->pet->healthRecords->isEmpty())
        <p class="sub" style="margin:4px 0 0">Pelanggan belum menambahkan catatan alergi atau vaksinasi pada profil hewan ini.</p>
      @else
        <p class="sub">Ringkasan kesehatan yang dibagikan pemilik hewan untuk keamanan layanan:</p>
        @foreach ($booking->pet->healthRecords as $hr)
          <div class="item" style="flex-direction:column;align-items:stretch;gap:4px">
            <div class="row sp">
              <div>
                <span class="pill {{ $hr->record_type === 'allergy' ? 'bad' : 'gray' }}">{{ $hr->type_label }}</span>
                <strong style="margin-left:6px">{{ $hr->title }}</strong>
              </div>
              @if ($hr->recorded_date)
                <small class="mut">{{ $hr->recorded_date->translatedFormat('d M Y') }}</small>
              @endif
            </div>
            @if ($hr->notes)<small class="mut">{{ $hr->notes }}</small>@endif
            @if ($hr->attachment_path)
              <div><a class="btn sm line" href="{{ route('pets.health.attachment', $hr->id) }}" target="_blank" rel="noopener">📎 Lihat lampiran</a></div>
            @endif
          </div>
        @endforeach
      @endif
    </div>

    {{-- CATATAN HASIL PERAWATAN OLEH OWNER (FITUR 6) --}}
    <div class="card mt">
      <h3>📝 Catatan hasil perawatan bisnis</h3>
      <p class="sub">Catat kondisi hewan dan hasil perawatan pada booking ini (terpisah dari catatan kesehatan pribadi pelanggan).</p>

      @foreach ($booking->careNotes as $cn)
        <div class="item" style="flex-direction:column;align-items:stretch;gap:4px">
          <div class="row sp">
            <div>
              <strong>{{ $cn->author->name ?? 'Tim Bisnis' }}</strong>
              <span class="pill {{ $cn->visible_to_customer ? 'done' : 'gray' }}" style="margin-left:6px;font-size:11px">
                {{ $cn->visible_to_customer ? 'Terlihat oleh pelanggan' : 'Internal bisnis' }}
              </span>
            </div>
            <small class="mut">{{ $cn->created_at?->translatedFormat('d M Y, H:i') }}</small>
          </div>
          <p class="sm" style="margin:4px 0">{{ $cn->care_summary }}</p>
          @if ($cn->recommendations)
            <small class="gold-t">Saran lanjutan: {{ $cn->recommendations }}</small>
          @endif
          <form method="POST" action="{{ route('owner.care-notes.destroy', $cn->id) }}" style="margin-top:4px" onsubmit="return confirm('Hapus catatan perawatan ini?')">
            @csrf @method('DELETE')
            <button class="btn sm danger" type="submit">Hapus</button>
          </form>
        </div>
      @endforeach

      <form method="POST" action="{{ route('owner.care-notes.store', $booking->id) }}" class="form mt">
        @csrf
        <x-field name="care_summary" label="Ringkasan hasil perawatan" type="textarea" rows="2" placeholder="Contoh: Mandi jamur selesai, bulu dikeringkan sempurna, nafsu makan baik." required />
        <x-field name="recommendations" label="Saran perawatan lanjutan (opsional)" type="textarea" rows="2" placeholder="Contoh: Sisir bulu setiap 2 hari sekali." />
        <label class="check">
          <input type="hidden" name="visible_to_customer" value="0">
          <input type="checkbox" name="visible_to_customer" value="1" checked>
          Tampilkan catatan perawatan ini pada halaman riwayat pelanggan
        </label>
        <div class="row" style="justify-content:flex-end">
          <button class="btn sm" type="submit">Simpan catatan perawatan</button>
        </div>
      </form>
    </div>
  </div>

  <div>
    <div class="card"><h3>Histori status</h3><p class="sub">Tercatat otomatis.</p>
      <ul class="tl">
        @foreach ($booking->statusLogs as $log)
          <li class="{{ $loop->last ? 'now' : 'ok' }}"><span class="dot">{{ $loop->last ? '●' : '✓' }}</span><b>{{ $log->status }}</b><small>{{ $log->changed_at->translatedFormat('d M Y, H.i') }} · {{ $log->changer->name }}@if($log->note) · {{ $log->note }}@endif</small></li>
        @endforeach
      </ul>
    </div>

    @if ($booking->review)
      <div class="card mt">
        <h3>⭐ Ulasan pelanggan</h3>
        <div class="gold-t b" style="margin:6px 0">{{ $booking->review->stars_label }} ({{ $booking->review->rating }}/5)</div>
        <p class="sm" style="margin:0">{{ $booking->review->comment ?: 'Tanpa komentar tertulis.' }}</p>
      </div>
    @endif
  </div>
</div>
@endsection
