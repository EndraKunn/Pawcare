@extends('layouts.dashboard')
@section('title', 'Ulasan Pelanggan')
@section('heading', 'Rating & ulasan pelanggan')
@section('subtitle', 'Ulasan terverifikasi khusus untuk '.$business->business_name.'.')

@section('content')
<div class="grid g3">
  <x-stat label="Rating rata-rata" :value="$summary['count'] > 0 ? number_format((float) $summary['avg'], 1, ',', '.').' / 5.0' : '—'" :note="$summary['count'].' ulasan sah'" />
  <x-stat label="Bintang 5 (★★★★★)" :value="$summary['distribution'][5] ?? 0" note="ulasan sangat puas" />
  <x-stat label="Perlu perhatian (1–2 ★)" :value="($summary['distribution'][1] ?? 0) + ($summary['distribution'][2] ?? 0)" :warn="true" />
</div>

<div class="card mt2">
  <div class="row sp wrapf" style="margin-bottom:14px">
    <div>
      <h3 style="margin:0">Daftar ulasan masuk</h3>
      <p class="sub" style="margin:2px 0 0">Owner dapat memberikan tanggapan resmi tanpa mengubah rating atau isi ulasan pelanggan.</p>
    </div>
    <form method="GET" action="{{ route('owner.reviews.index') }}" class="row">
      <label class="fld">
        <select name="rating" onchange="this.form.submit()">
          <option value="0">Semua bintang</option>
          @foreach ([5, 4, 3, 2, 1] as $star)
            <option value="{{ $star }}" @selected($ratingFilter === $star)>{{ $star }} ★ ({{ $summary['distribution'][$star] ?? 0 }})</option>
          @endforeach
        </select>
      </label>
    </form>
  </div>

  @forelse ($reviews as $rev)
    <div class="item" style="flex-direction:column;align-items:stretch;gap:8px;padding:16px 0">
      <div class="row sp wrapf">
        <div>
          <strong>{{ $rev->customer?->name ?? 'Pelanggan' }}</strong>
          <span class="gold-t b" style="margin-left:8px">{{ $rev->stars_label }} ({{ $rev->rating }}/5)</span>
          @if ($rev->booking)
            <a class="sm mut" style="margin-left:8px" href="{{ route('owner.bookings.show', $rev->booking_id) }}">{{ $rev->booking->invoice_number }}</a>
          @endif
        </div>
        <small class="mut">{{ $rev->created_at?->translatedFormat('d M Y, H:i') }}</small>
      </div>

      <p style="margin:2px 0">{{ $rev->comment ?: 'Pelanggan memberikan rating tanpa komentar tertulis.' }}</p>

      @if ($rev->reply)
        <div style="padding:10px 14px;border-left:3px solid var(--gold);background:rgba(224,182,74,.08);border-radius:8px">
          <small class="gold-t b" style="display:block">Tanggapan Anda · {{ $rev->reply->updated_at?->translatedFormat('d M Y, H:i') }}</small>
          <p class="sm" style="margin:4px 0 0">{{ $rev->reply->reply }}</p>
        </div>
      @endif

      <form method="POST" action="{{ route('owner.reviews.reply', $rev->id) }}" class="row wrapf" style="align-items:flex-end;gap:10px;margin-top:4px">
        @csrf
        <label class="fld" style="flex:1;min-width:240px">
          <input type="text" name="reply" maxlength="1000" value="{{ old('reply', $rev->reply?->reply) }}" placeholder="Tulis atau perbarui tanggapan bisnis untuk ulasan ini..." required>
        </label>
        <button class="btn sm line" type="submit">{{ $rev->reply ? 'Perbarui tanggapan' : 'Kirim tanggapan' }}</button>
      </form>
    </div>
  @empty
    <x-empty icon="⭐" title="Belum ada ulasan pelanggan" text="Ulasan akan muncul di sini setelah pelanggan menyelesaikan booking dan memberikan rating." />
  @endforelse

  <div class="mt">{{ $reviews->links() }}</div>
</div>
@endsection
