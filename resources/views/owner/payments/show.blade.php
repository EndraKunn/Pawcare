@extends('layouts.dashboard')
@section('title', 'Verifikasi Pembayaran')
@section('heading', 'Verifikasi pembayaran')
@section('subtitle', $payment->booking->invoice_number)
@section('actions')<a class="btn line" href="{{ route('owner.payments.index') }}">← Kembali</a>@endsection

@section('content')
@php $pay = $payment; $isPdf = $pay->proof_path && strtolower(pathinfo($pay->proof_path, PATHINFO_EXTENSION)) === 'pdf'; @endphp
<div class="grid g2">
  <div class="card"><div class="row sp"><h3>Tagihan</h3><x-pay :status="$pay->status" /></div>
    <dl class="kv mt"><dt>Pelanggan</dt><dd>{{ $pay->booking->customer->name }}</dd><dt>Invoice</dt><dd><a class="gold-t" href="{{ route('owner.bookings.show', $pay->booking_id) }}">{{ $pay->booking->invoice_number }}</a></dd><dt>Tagihan</dt><dd class="b">{{ rupiah($pay->amount) }}</dd>
      <dt>Metode</dt><dd>{{ $pay->payment_method === 'payment_gateway' ? 'Payment Gateway ('.strtoupper((string) $pay->gateway_provider).')' : 'Transfer Manual' }}</dd>
      @if ($pay->gateway_reference)<dt>Ref Gateway</dt><dd>{{ $pay->gateway_reference }} ({{ $pay->gateway_status_label ?? $pay->gateway_status }})</dd>@endif
      @if ($pay->verified_at)<dt>Diproses</dt><dd>{{ $pay->verified_at->translatedFormat('d M Y, H.i') }} oleh {{ $pay->verifier->name ?? 'Sistem Gateway' }}</dd>@endif
      @if ($pay->reject_reason)<dt>Alasan tolak</dt><dd style="color:var(--bad)">{{ $pay->reject_reason }}</dd>@endif</dl>
    <hr>
    @if ($pay->status === 'Menunggu Verifikasi')
      <form method="POST" action="{{ route('owner.payments.verify', $pay->id) }}" data-confirm="Tandai pembayaran ini sebagai Dibayar?">@csrf @method('PATCH')<button class="btn ok block" type="submit">✔ Verifikasi pembayaran</button></form>
      <form method="POST" action="{{ route('owner.payments.reject', $pay->id) }}" class="mt">@csrf @method('PATCH')
        <x-field name="reason" label="Alasan penolakan (wajib jika ditolak)" type="textarea" placeholder="Contoh: nominal tidak sesuai" />
        <div class="mt"><button class="btn danger block" type="submit">Tolak bukti pembayaran</button></div>
      </form>
    @else
      <p class="sm mut" style="margin:0">Tidak ada tindakan yang diperlukan.</p>
    @endif
  </div>
  <div class="card"><h3>Bukti pembayaran</h3>
    @if ($pay->proof_path)
      @if ($isPdf)
        <p class="sub">Berkas PDF</p><a class="btn sm" href="{{ route('payments.proof', $pay->id) }}" target="_blank" rel="noopener">Buka PDF</a>
      @else
        <img class="proof" src="{{ route('payments.proof', $pay->id) }}" alt="Bukti pembayaran {{ $pay->booking->invoice_number }}">
      @endif
    @else
      <x-empty icon="🧾" title="Belum ada bukti" text="Pelanggan belum mengunggah bukti pembayaran." />
    @endif
  </div>
</div>
@endsection
