@extends('layouts.dashboard')
@section('title', 'Pembayaran')
@section('heading', 'Pembayaran')
@section('subtitle', 'Invoice '.$booking->invoice_number)
@section('actions')<a class="btn line" href="{{ route('customer.bookings.show', $booking->id) }}">← Detail booking</a>@endsection

@section('content')
@php
  $pay = $booking->payment;
  $biz = $booking->business;
  $channels = $gatewayChannels ?? \App\Services\PaymentGatewayService::CHANNELS;
@endphp
<div class="grid g2">
  <div>
    <div class="card">
      <h3>Rincian tagihan</h3>
      <p class="sub">{{ $biz->business_name }} · {{ $booking->pet->name }}</p>
      @foreach ($booking->items as $i)
        <div class="sum-row"><span>{{ $i->service->name }} × {{ $i->quantity }} {{ $i->service->unit }}</span><b>{{ rupiah($i->subtotal) }}</b></div>
      @endforeach
      <div class="total"><span>Total pembayaran</span><b>{{ rupiah($pay->amount) }}</b></div>
      <div class="mt row wrapf" style="gap:8px">
        <x-pay :status="$pay->status" />
        @if ($pay->gateway_status)
          <span class="pill gray">{{ $pay->gateway_status_label }}</span>
        @endif
      </div>

      <hr>
      <h3>Rekening transfer manual</h3>
      @if ($biz->bank_account_number)
        <div class="bank mt"><div class="lgo">{{ strtoupper(substr($biz->bank_name ?? 'BANK', 0, 4)) }}</div><div><div class="no">{{ $biz->bank_account_number }}</div><small class="mut">a.n. {{ $biz->bank_account_name ?? $biz->business_name }}</small></div></div>
      @else
        <p class="sm mut">Bisnis belum mengisi rekening. Anda dapat menggunakan metode Payment Gateway atau menghubungi bisnis.</p>
      @endif
    </div>

    {{-- METODE 1: PAYMENT GATEWAY (MIDTRANS SANDBOX) --}}
    @if ($booking->status !== 'Dibatalkan' && $pay->status !== 'Dibayar')
      <div class="card gold mt">
        <h3>⚡ Bayar Instan via Payment Gateway (Sandbox)</h3>
        <p class="sub">Pembayaran diverifikasi otomatis melalui webhook terenkripsi SHA-512.</p>

        @if ($pay->gateway_reference)
          <div style="padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:var(--field);margin-bottom:14px">
            <div class="row sp wrapf">
              <strong class="gold-t">{{ $pay->gateway_reference }}</strong>
              <span class="pill wait">{{ $pay->gateway_status_label ?? 'Menunggu' }}</span>
            </div>
            <small class="mut" style="display:block;margin-top:4px">
              Metode: <b>{{ $channels[$pay->gateway_payment_type] ?? strtoupper((string) $pay->gateway_payment_type) }}</b>
              · ID Transaksi: {{ $pay->gateway_transaction_id }}
            </small>
            @if ($pay->gateway_expires_at)
              <small class="mut" style="display:block">Berlaku hingga: {{ $pay->gateway_expires_at->translatedFormat('d M Y, H:i') }}</small>
            @endif
          </div>
        @endif

        <form method="POST" action="{{ route('customer.bookings.pay.gateway', $booking->id) }}" class="form">
          @csrf
          <x-field name="channel" label="Pilih metode pembayaran gateway" type="select" :options="$channels" :value="$pay->gateway_payment_type ?? 'qris'" required />
          <button class="btn block" type="submit">
            {{ $pay->gateway_reference ? 'Perbarui Sesi Payment Gateway' : 'Buat Pembayaran Payment Gateway' }}
          </button>
        </form>
        <p class="note">Nominal diambil langsung dari tagihan server ({{ rupiah($pay->amount) }}). Status menjadi Dibayar setelah webhook terverifikasi diterima server.</p>
      </div>
    @endif
  </div>

  <div class="card">
    <h3>Unggah bukti pembayaran (Transfer Manual)</h3>
    <p class="sub">Format JPG, PNG, WebP, atau PDF. Maksimal 2 MB.</p>
    @if ($booking->status === 'Dibatalkan')
      <p class="flash err">Booking dibatalkan, tidak dapat dibayar.</p>
    @elseif ($pay->status === 'Dibayar')
      <p class="flash ok">✅ Pembayaran sudah diverifikasi pada {{ $pay->paid_at?->translatedFormat('d M Y, H.i') }}.</p>
    @elseif ($pay->status === 'Menunggu Verifikasi')
      <p class="flash ok">⏳ Bukti pembayaran sudah dikirim dan menunggu verifikasi bisnis.</p>
      <a class="btn sm line" href="{{ route('payments.proof', $pay->id) }}" target="_blank" rel="noopener">Lihat bukti yang dikirim</a>
    @else
      @if ($pay->status === 'Ditolak')<p class="flash err">Bukti / pembayaran ditolak: {{ $pay->reject_reason }}. Silakan unggah ulang atau gunakan Payment Gateway.</p>@endif
      <form method="POST" action="{{ route('customer.bookings.pay.store', $booking->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="drop"><div class="big">🧾</div><p class="b" style="margin:4px 0">Pilih file bukti transfer</p>
          <input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
        </div>
        @error('proof')<p class="err-t">{{ $message }}</p>@enderror
        <button class="btn block mt" type="submit">Kirim bukti pembayaran</button>
      </form>
      <p class="note">Setelah dikirim, status berubah menjadi Menunggu Verifikasi sampai bisnis memeriksanya.</p>
    @endif
  </div>
</div>
@endsection