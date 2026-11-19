@extends('layouts.public')
@section('title', 'Katalog Bisnis')

@section('content')
<h2 style="margin:0 0 4px;font-size:26px">Temukan mitra terbaik</h2>
<p class="mut" style="margin:0 0 20px">Bandingkan bisnis penitipan dan perawatan hewan secara reaktif. Setiap bisnis memiliki layanan, jadwal, kapasitas slot, dan harga sendiri.</p>

<livewire:endra-catalog-browser />
@endsection
