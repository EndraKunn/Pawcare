@extends('layouts.dashboard')

@section('title', 'Verifikasi Email')
@section('heading', 'Verifikasi Email')
@section('subtitle', 'Selesaikan verifikasi akun PawCare Anda.')

@section('content')
<div class="card">
    <h3>Verifikasi Alamat Email</h3>

    <p class="sub">
        Terima kasih telah mendaftar di PawCare.
        Sebelum menggunakan seluruh fitur aplikasi,
        silakan verifikasi alamat email Anda melalui
        tautan yang telah kami kirimkan.
    </p>

    @if (session('success'))
        <p class="flash ok">{{ session('success') }}</p>
    @endif

    @if (session('status') === 'verification-link-sent')
        <p class="flash ok">
            Tautan verifikasi baru berhasil dikirim.
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf

        <button type="submit" class="btn">
            Kirim Ulang Email Verifikasi
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt">
        @csrf

        <button type="submit" class="btn line">
            Keluar
        </button>
    </form>
</div>
@endsection
