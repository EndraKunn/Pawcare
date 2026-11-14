@extends('layouts.dashboard')
@section('title', 'Keranjang Booking')
@section('heading', 'Keranjang booking')
@section('subtitle', 'Periksa semua jadwal sebelum checkout dalam satu order.')
@section('actions')<a class="btn line" href="{{ route('catalog') }}">+ Tambah layanan</a>@endsection

@section('content')
@if (empty($groups))
  <div class="card">
    <x-empty icon="🛒" title="Keranjang masih kosong" />
    <div class="center mt"><a class="btn" href="{{ route('catalog') }}">Cari layanan PawCare</a></div>
  </div>
@else
  <div class="grid g21">
    <div>
      @foreach ($groups as $group)
        <div class="card mb">
          <div class="row sp wrapf">
            <div>
              <h3 style="margin-bottom:4px">{{ $group['business_name'] }}</h3>
              <p class="sub" style="margin:0">{{ $group['pet_name'] }} · {{ $group['pet_species'] ?? 'Hewan' }}</p>
              <p class="sm mut" style="margin:5px 0 0">{{ \Carbon\Carbon::parse($group['booking_date'])->translatedFormat('d M Y') }} · {{ $group['booking_time'] }}</p>
            </div>
            <form method="POST" action="{{ route('customer.bookings.cart.remove', $group['id']) }}">
              @csrf
              @method('DELETE')
              <button class="btn sm line" type="submit">Hapus jadwal</button>
            </form>
          </div>
          <div class="tw mt">
            <table>
              <thead><tr><th>Layanan</th><th>Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
              <tbody>
                @foreach ($group['items'] as $item)
                  <tr>
                    <td><b>{{ $item['name'] }}</b><small class="mut" style="display:block">{{ $item['category'] }} · per {{ $item['unit'] }}</small></td>
                    <td>{{ $item['quantity'] }}</td>
                    <td class="num nw">{{ rupiah($item['unit_price']) }}</td>
                    <td class="num nw">{{ rupiah($item['unit_price'] * $item['quantity']) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @if (!empty($group['notes']))<p class="sm mut mt">Catatan: {{ $group['notes'] }}</p>@endif
        </div>
      @endforeach
    </div>

    <aside>
      <div class="card gold" style="position:sticky;top:12px">
        <h3>Ringkasan checkout</h3>
        <p class="sub">{{ count($groups) }} jadwal · {{ $itemCount }} jenis layanan</p>
        <div class="total"><span>Total perkiraan</span><b>{{ rupiah($total) }}</b></div>
        <p class="note">Harga, status layanan, kepemilikan hewan, dan ketersediaan slot akan divalidasi ulang oleh server. Jika harga berubah, keranjang diperbarui dan Anda diminta mengonfirmasi kembali.</p>
        <form method="POST" action="{{ route('customer.bookings.cart.checkout') }}">
          @csrf
          <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
          <button class="btn block lg mt" type="submit">Checkout semua jadwal</button>
        </form>
        @error('cart')<p class="err-t mt">{{ $message }}</p>@enderror
      </div>
    </aside>
  </div>
@endif
@endsection
