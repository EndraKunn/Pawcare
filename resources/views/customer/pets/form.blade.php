@extends('layouts.dashboard')
@php $isEdit = $pet->exists; @endphp
@section('title', $isEdit ? 'Ubah Hewan' : 'Tambah Hewan')
@section('heading', $isEdit ? 'Ubah data hewan' : 'Tambah hewan baru')
@section('subtitle', 'Data hewan dipakai ulang setiap kali booking.')

@section('content')
<div class="card" style="max-width:640px">
  <form method="POST" action="{{ $isEdit ? route('customer.pets.update', $pet->id) : route('customer.pets.store') }}" class="form" enctype="multipart/form-data">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <div class="form f2">
      <x-field name="name" label="Nama hewan" :value="$pet->name" placeholder="Contoh: Milo" required />
      <x-field name="species" label="Jenis hewan" type="select" :options="array_combine(\App\Models\Pet::SPECIES, \App\Models\Pet::SPECIES)" :value="$pet->species" required />
    </div>
    <div class="form f2">
      <x-field name="breed" label="Ras" :value="$pet->breed" placeholder="Contoh: Golden Retriever" />
      <x-field name="age_years" label="Umur (tahun)" type="number" min="0" max="40" :value="$pet->age !== null ? intdiv($pet->age, 12) : null" hint="Disimpan dalam bulan di database" />
    </div>
    @php $g = old('gender', $pet->gender ?? 'Jantan'); @endphp
    <div class="fld"><span>Jenis kelamin</span>
      <div class="radios">
        <label><input type="radio" name="gender" value="Jantan" @checked($g === 'Jantan')><div>♂ Jantan</div></label>
        <label><input type="radio" name="gender" value="Betina" @checked($g === 'Betina')><div>♀ Betina</div></label>
      </div>
      @error('gender')<em class="err-t" style="font-style:normal">{{ $message }}</em>@enderror
    </div>
    <div class="fld"><span>Foto hewan</span>
      <div class="upload">
        <img id="photoPreview" class="prev" src="{{ $pet->photo_url ?? \App\Support\PawcareImage::fallback('pet-anjing.svg') }}" alt="Pratinjau foto hewan">
        <div>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="photoPreview">
          <em class="h" style="font-style:normal;display:block;margin-top:6px">JPG, PNG, atau WebP, maksimal 2 MB. Kosongkan untuk memakai gambar bawaan.</em>
          @if ($isEdit && $pet->has_photo)
            <label class="check" style="margin-top:8px"><input type="checkbox" name="remove_photo" value="1"> Hapus foto saat ini</label>
          @endif
        </div>
      </div>
      @error('photo')<em class="err-t" style="font-style:normal">{{ $message }}</em>@enderror
    </div>
    <div class="row" style="justify-content:flex-end">
      <a class="btn line" href="{{ route('customer.pets.index') }}">Batal</a>
      <button class="btn" type="submit">Simpan hewan</button>
    </div>
  </form>
</div>
@endsection
