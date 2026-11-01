@extends('layouts.dashboard')

@section('title', 'Notifikasi')
@section('heading', 'Notifikasi')
@section('subtitle', 'Pembaruan booking dan pembayaran PawCare.')

@section('content')
    <div class="grid g3" style="margin-bottom:20px">
        <div class="card">
            <p class="sub">Total notifikasi</p>
            <h2>{{ $notifications->total() }}</h2>
        </div>

        <div class="card">
            <p class="sub">Belum dibaca</p>
            <h2 class="gold-t">{{ $unreadCount }}</h2>
        </div>

        <div class="card">
            <p class="sub">Sudah dibaca</p>
            <h2>{{ max(0, $notifications->total() - $unreadCount) }}</h2>
        </div>
    </div>

    <div class="card">
        <div class="row" style="justify-content:space-between; margin-bottom:16px; flex-wrap:wrap">
            <h3 style="margin:0">Riwayat pemberitahuan</h3>

            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('customer.notifications.read-all') }}">
                    @csrf
                    <button class="btn sm line" type="submit">
                        ✓ Tandai semua dibaca
                    </button>
                </form>
            @endif
        </div>

        @forelse ($notifications as $notification)
            @php
                $title = data_get($notification->data, 'title', 'Pemberitahuan PawCare');
                $message = data_get($notification->data, 'message', 'Ada pembaruan pada akun Anda.');
                $isUnread = is_null($notification->read_at);
            @endphp

            <div class="item {{ $isUnread ? 'sel' : '' }}" style="align-items:flex-start; gap:14px; padding:18px 12px">
                <div style="font-size:26px">
                    {{ str_contains(strtolower($title), 'pembayaran') ? '💳' : '🔔' }}
                </div>

                <div style="flex:1; min-width:0">
                    <div class="row" style="justify-content:space-between; gap:8px; flex-wrap:wrap">
                        <strong>{{ $title }}</strong>

                        @if ($isUnread)
                            <span class="pill wait pending">Belum dibaca</span>
                        @else
                            <span class="pill gray">Sudah dibaca</span>
                        @endif
                    </div>

                    <p class="sub" style="margin:8px 0">
                        {{ $message }}
                    </p>

                    <small class="mut">
                        {{ $notification->created_at->translatedFormat('d M Y, H:i') }}
                    </small>

                    <div style="margin-top:12px">
                        <a
                            class="btn sm {{ $isUnread ? '' : 'line' }}"
                            href="{{ route('customer.notifications.open', $notification->id) }}"
                        >
                            {{ str_contains(strtolower($title), 'ditolak') ? 'Buka & unggah ulang bukti' : 'Lihat detail' }}
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <x-empty
                icon="🔔"
                title="Belum ada notifikasi"
                text="Pemberitahuan booking dan pembayaran akan muncul di sini."
            />
        @endforelse

        <div style="margin-top:20px">
            {{ $notifications->links() }}
        </div>
    </div>
@endsection
