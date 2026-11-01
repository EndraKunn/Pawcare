@extends('layouts.dashboard')
@section('title', 'Layanan & Harga')
@section('heading', 'Layanan & harga')
@section('subtitle', 'Layanan milik '.$business->business_name.' — bisnis lain tidak melihat atau mengubahnya.')

@section('content')
<div class="grid g21">
  <div>
    @if ($services->isEmpty())
      <div class="card"><x-empty icon="✂️" title="Belum ada layanan" text="Tambahkan layanan pertama Anda di formulir sebelah." /></div>
    @else
      <div class="tw"><table><thead><tr><th>Layanan</th><th>Jenis</th><th class="num">Harga</th><th>Satuan / Durasi</th><th>Aktif</th><th></th></tr></thead><tbody>
        @foreach ($services as $s)
          <tr>
            <td><b>{{ $s->name }}</b>@if($s->description)<br><small class="mut">{{ \Illuminate\Support\Str::limit($s->description, 50) }}</small>@endif</td>
            <td>{{ $s->category }}</td><td class="num nw">{{ rupiah($s->price) }}</td><td>per {{ $s->unit }} ({{ $s->duration_minutes ?? 60 }} mnt)</td>
            <td><form method="POST" action="{{ route('owner.services.toggle', $s->id) }}">@csrf @method('PATCH')<button type="submit" class="toggle {{ $s->status ? 'on' : '' }}" style="border:0;padding:0;cursor:pointer" aria-label="Ubah status aktif"></button></form></td>
            <td><div class="acts">
              <a class="btn sm line" href="{{ route('owner.services.edit', $s->id) }}">Ubah</a>
              <form method="POST" action="{{ route('owner.services.destroy', $s->id) }}" data-confirm="Hapus layanan {{ $s->name }}?">@csrf @method('DELETE')<button class="btn sm danger" type="submit">Hapus</button></form>
            </div></td>
          </tr>
        @endforeach
      </tbody></table></div>
      {{ $services->links() }}
    @endif
  </div>
  <div class="card"><h3>Tambah layanan</h3><p class="sub">Layanan baru langsung tersedia untuk booking.</p>
    <form method="POST" action="{{ route('owner.services.store') }}" class="form">
      @csrf
      <x-field name="name" label="Nama layanan" placeholder="Contoh: Grooming Kucing" required />
      <div class="form f2">
        <x-field name="category" label="Jenis" type="select" :options="array_combine(\App\Models\Service::CATEGORIES, \App\Models\Service::CATEGORIES)" required />
        <x-field name="unit" label="Satuan" type="select" :options="['hari' => 'per hari', 'sesi' => 'per sesi']" required />
      </div>
      <div class="form f2">
        <x-field name="price" label="Harga (Rp)" type="number" min="0" step="1000" placeholder="85000" required />
        <x-field name="duration_minutes" label="Durasi (menit)" type="number" min="15" max="480" value="60" required />
      </div>
      <x-field name="description" label="Deskripsi" type="textarea" placeholder="Keterangan layanan" />
      <input type="hidden" name="status" value="1">
      <button class="btn block" type="submit">Simpan layanan</button>
    </form>
  </div>
</div>
@endsection
