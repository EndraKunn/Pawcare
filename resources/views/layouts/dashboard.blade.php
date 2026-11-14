@extends('layouts.base')

@section('body')
@php
    $u = auth()->user();

    // Hitung notifikasi yang belum dibaca khusus Customer.
    $unreadNotifications = $u->isCustomer()
        ? $u->unreadNotifications()->count()
        : 0;

    // Hitung pesan chat belum dibaca untuk Customer atau Owner.
    $unreadChatCount = 0;
    if (\Illuminate\Support\Facades\Schema::hasTable('conversation_messages')) {
        if ($u->isCustomer()) {
            $unreadChatCount = \App\Models\ConversationMessage::query()
                ->whereHas('conversation', fn ($c) => $c->where('customer_id', $u->id))
                ->where('sender_id', '!=', $u->id)
                ->whereNull('read_at')
                ->count();
        } elseif ($u->isOwner()) {
            $unreadChatCount = \App\Models\ConversationMessage::query()
                ->whereHas('conversation.business', fn ($b) => $b->where('owner_id', $u->id))
                ->where('sender_id', '!=', $u->id)
                ->whereNull('read_at')
                ->count();
        }
    }

    $nav = match (true) {
        $u->isAdmin() => [
            ['📊', 'Dashboard', 'admin.dashboard', 'admin.dashboard'],
            ['👤', 'Pengguna', 'admin.users.index', 'admin.users.*'],
            ['🏢', 'Manajemen Klien', 'admin.clients.index', 'admin.clients.*'],
            ['✂️', 'Layanan', 'admin.services', 'admin.services'],
            ['📅', 'Booking', 'admin.bookings', 'admin.bookings'],
            ['💳', 'Pembayaran', 'admin.payments', 'admin.payments'],
            ['📈', 'Laporan Sistem', 'admin.reports', 'admin.reports'],
            ['⚙️', 'Profil', 'profile.edit', 'profile.*'],
        ],

        $u->isOwner() => [
            ['📊', 'Dashboard', 'owner.dashboard', 'owner.dashboard'],
            ['🏢', 'Profil Bisnis', 'owner.business.edit', 'owner.business.*'],
            ['🗓️', 'Kalender & Slot', 'owner.calendar.index', 'owner.calendar.*'],
            ['🕒', 'Pengaturan Pre-order', 'owner.jeni.preorder.edit', 'owner.jeni.preorder.*'],
            ['✂️', 'Layanan', 'owner.services.index', 'owner.services.*'],
            ['👥', 'Pelanggan', 'owner.customers.index', 'owner.customers.*'],
            ['📅', 'Booking Masuk', 'owner.bookings.index', 'owner.bookings.*'],
            ['💳', 'Pembayaran', 'owner.payments.index', 'owner.payments.*'],
            ['⭐', 'Ulasan', 'owner.reviews.index', 'owner.reviews.*'],
            ['💬', 'Pesan', 'owner.chat.index', 'owner.chat.*'],
            ['🧾', 'Transaksi', 'owner.transactions', 'owner.transactions'],
            ['📈', 'Laporan', 'owner.reports', 'owner.reports'],
            ['👤', 'Akun', 'profile.edit', 'profile.*'],
        ],

        default => [
            ['🏠', 'Dashboard', 'customer.dashboard', 'customer.dashboard'],
            ['🐾', 'Data Hewan', 'customer.pets.index', 'customer.pets.*'],
            ['🔎', 'Cari Layanan', 'catalog', 'catalog*'],
            ['📅', 'Booking Saya', 'customer.bookings.index', 'customer.bookings.*'],
            ['🗓️', 'Cek Slot & Pre-order', 'customer.jeni.calendar.index', 'customer.jeni.calendar.*'],
            ['💬', 'Pesan', 'customer.chat.index', 'customer.chat.*'],
            ['🔔', 'Notifikasi', 'customer.notifications.index', 'customer.notifications.*'],
            ['🧾', 'Riwayat Transaksi', 'customer.transactions', 'customer.transactions'],
            ['👤', 'Profil', 'profile.edit', 'profile.*'],
        ],
    };

    $roleText = match (true) {
        $u->isAdmin() => 'Area admin platform · role: admin',
        $u->isOwner() => 'Area pemilik bisnis · role: business_owner',
        default => 'Area pelanggan · role: customer',
    };

    $bizName = $u->isOwner()
        ? optional($u->primaryBusiness())->business_name
        : null;
@endphp

<div class="frame">
    <div class="app">
        <aside class="side">
            <div class="lg">🐾 Paw<b>Care</b></div>

            <div class="role">{{ $roleText }}</div>

            @foreach ($nav as [$icon, $label, $route, $pattern])
                <a
                    href="{{ route($route) }}"
                    class="{{ request()->routeIs($pattern) ? 'on' : '' }}"
                    @if (request()->routeIs($pattern))
                        aria-current="page"
                    @endif
                >
                    <span aria-hidden="true">{{ $icon }}</span>

                    <span>{{ $label }}</span>

                    @if (
                        $route === 'customer.notifications.index'
                        && $unreadNotifications > 0
                    )
                        <span
                            aria-label="{{ $unreadNotifications }} notifikasi belum dibaca"
                            style="
                                margin-left:auto;
                                min-width:20px;
                                padding:2px 6px;
                                border-radius:12px;
                                background:#e5b63f;
                                color:#102044;
                                font-size:12px;
                                font-weight:700;
                                text-align:center;
                            "
                        >
                            {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                        </span>
                    @endif

                    @if (
                        in_array($route, ['customer.chat.index', 'owner.chat.index'], true)
                        && $unreadChatCount > 0
                    )
                        <span
                            aria-label="{{ $unreadChatCount }} pesan belum dibaca"
                            style="
                                margin-left:auto;
                                min-width:20px;
                                padding:2px 6px;
                                border-radius:12px;
                                background:#3ecf8e;
                                color:#062616;
                                font-size:12px;
                                font-weight:700;
                                text-align:center;
                            "
                        >
                            {{ $unreadChatCount > 99 ? '99+' : $unreadChatCount }}
                        </span>
                    @endif
                </a>
            @endforeach

            <div class="me">
                <span class="av {{ $u->isCustomer() ? 'bl' : '' }}">
                    {{ initials($u->name) }}
                </span>

                <div>
                    <span class="b sm">{{ $u->name }}</span>
                    <small>{{ $bizName ?? $u->roleLabel() }}</small>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn sm danger out">
                    🚪 Keluar
                </button>
            </form>
        </aside>

        <section class="main">
            <div class="top">
                <div>
                    @if ($bizName)
                        <div class="biz-eyebrow">🏨 {{ $bizName }}</div>
                    @endif

                    <h2>@yield('heading')</h2>
                    <p>@yield('subtitle')</p>
                </div>

                <div class="row">
                    @yield('actions')
                </div>
            </div>

            @include('partials.flash')

            @yield('content')
        </section>
    </div>
</div>
@endsection
