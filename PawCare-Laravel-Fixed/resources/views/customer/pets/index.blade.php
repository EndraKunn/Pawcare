@extends('layouts.dashboard')

@section('title', 'Data Hewan')
@section('heading', 'Data hewan')
@section('subtitle', 'Setiap hewan tercatat atas nama pemiliknya dan dipakai ulang setiap kali booking.')

@section('actions')
    <a class="btn" href="{{ route('customer.pets.create') }}">
        + Tambah hewan
    </a>
@endsection

@section('content')
    @if ($pets->isEmpty())
        <div class="card">
            <x-empty
                icon="🐾"
                title="Belum ada data hewan"
                text="Tambahkan hewan kesayangan Anda untuk mulai booking."
            />
        </div>
    @else
        <div class="grid g3">
            @foreach ($pets as $p)
                @php
                    $isArchived = ! is_null($p->archived_at);
                    $hasBooking = ($p->bookings_count ?? 0) > 0;
                @endphp

                <div class="card petc">
                    <div class="ring">
                        <x-photo
                            :src="$p->photo_url"
                            :fallback="$p->photo_fallback_url"
                            :alt="'Foto '.$p->name"
                        />
                    </div>

                    @if ($isArchived)
                        <p class="sm" style="color:#e5b63f">
                            📦 Hewan diarsipkan
                        </p>
                    @endif

                    <h3>{{ $p->name }}</h3>

                    <div class="meta">
                        {{ $p->species }}
                        · {{ $p->breed ?? '-' }}
                        · {{ $p->age_label }}
                        · {{ $p->gender }}
                    </div>

                    <div
                        class="row"
                        style="justify-content:center;flex-wrap:wrap;gap:8px"
                    >
                        <a
                            class="btn sm"
                            href="{{ route('customer.pets.health.show', $p->id) }}"
                        >
                            🩺 Kesehatan &amp; Riwayat
                        </a>

                        @if ($isArchived)
                            <form
                                method="POST"
                                action="{{ route('customer.pets.restore', $p->id) }}"
                                class="inline"
                                onsubmit="return confirm('Pulihkan {{ $p->name }} agar dapat digunakan untuk booking kembali?')"
                            >
                                @csrf
                                @method('PATCH')

                                <button class="btn sm line" type="submit">
                                    Pulihkan
                                </button>
                            </form>
                        @else
                            <a
                                class="btn sm line"
                                href="{{ route('customer.pets.edit', $p->id) }}"
                            >
                                Ubah
                            </a>

                            @if ($hasBooking)
                                <form
                                    method="POST"
                                    action="{{ route('customer.pets.archive', $p->id) }}"
                                    class="inline"
                                    onsubmit="return confirm('Arsipkan {{ $p->name }}? Riwayat booking dan pembayaran akan tetap tersimpan.')"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button class="btn sm line" type="submit">
                                        Arsipkan
                                    </button>
                                </form>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('customer.pets.destroy', $p->id) }}"
                                    class="inline"
                                    onsubmit="return confirm('Hapus data {{ $p->name }} secara permanen?')"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button class="btn sm danger" type="submit">
                                        Hapus
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{ $pets->links() }}
    @endif
@endsection
