@extends('layouts.dashboard')
@section('title','Withdrawal')
@section('heading','Penarikan saldo bisnis')
@section('subtitle','Saldo tersedia berasal dari settlement pembayaran yang telah tercatat.')
@section('content')
<div class="grid g2">
  <div class="card"><div class="sub">Saldo tersedia</div><h2>{{ rupiah($balance) }}</h2></div>
  <div class="card"><h3>Ajukan withdrawal</h3>
    <form method="POST" action="{{ route('owner.withdrawals.store') }}" class="form">@csrf
      <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
      <x-field name="amount" label="Nominal (Rp)" type="number" min="1" step="1" required />
      <x-field name="bank_name" label="Nama bank" required />
      <x-field name="bank_account_number" label="Nomor rekening" required />
      <x-field name="bank_account_name" label="Nama pemilik rekening" required />
      <button class="btn">Kirim permintaan</button>
    </form>
  </div>
</div>
<div class="card mt2"><h3>Riwayat withdrawal</h3><div class="tw"><table><thead><tr><th>Tanggal</th><th>Nominal</th><th>Bank</th><th>Status</th><th>Referensi</th></tr></thead><tbody>
@forelse($withdrawals as $w)<tr><td>{{ $w->created_at->format('d/m/Y H:i') }}</td><td>{{ rupiah($w->amount) }}</td><td>{{ $w->bank_name }} · {{ $w->bank_account_number }}</td><td>{{ ucfirst($w->status) }}</td><td>{{ $w->reference ?? '-' }}</td></tr>@empty<tr><td colspan="5">Belum ada withdrawal.</td></tr>@endforelse
</tbody></table></div>{{ $withdrawals->links() }}</div>
@endsection
