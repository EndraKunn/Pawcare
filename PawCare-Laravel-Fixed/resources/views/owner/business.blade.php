@extends('layouts.dashboard')

@section('title', 'Profil Bisnis')
@section('heading', 'Profil bisnis')
@section('subtitle', 'Kelola informasi bisnis, jenis hewan yang diterima, foto sampul, rekening, jadwal operasional, kapasitas slot, dan hari libur.')

@section('content')

{{-- INFORMASI BISNIS --}}
<form method="POST"
      action="{{ route('owner.business.update') }}"
      enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="accepted_species_submitted" value="1">

    @php
        $selectedSpecies = old('accepted_species', $business->accepted_species_list ?: \App\Models\Pet::SPECIES);
    @endphp

    <div class="grid g21">
        <div class="card">
            <h3>Informasi bisnis</h3>
            <p class="sub">Informasi yang ditampilkan kepada pelanggan di katalog.</p>

            <div class="form">
                <x-field
                    name="business_name"
                    label="Nama bisnis"
                    :value="$business->business_name"
                    required
                />

                <x-field
                    name="address"
                    label="Alamat bisnis"
                    type="textarea"
                    :value="$business->address"
                    required
                />

                <x-field
                    name="description"
                    label="Deskripsi bisnis"
                    type="textarea"
                    rows="4"
                    :value="$business->description"
                    placeholder="Ceritakan layanan dan fasilitas bisnis Anda"
                />

                <div class="fld">
                    <span>Jenis hewan yang diterima <b class="gold-t">*</b></span>
                    <div class="chk-grid" style="margin-top:4px">
                        @foreach (\App\Models\Pet::SPECIES as $sp)
                            <label class="check" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;background:var(--field)">
                                <input
                                    type="checkbox"
                                    name="accepted_species[]"
                                    value="{{ $sp }}"
                                    @checked(is_array($selectedSpecies) && in_array($sp, $selectedSpecies, true))
                                >
                                {{ $sp }}
                            </label>
                        @endforeach
                    </div>
                    <em class="h" style="font-style:normal">Digunakan pada filter pencarian jenis hewan di katalog publik.</em>
                    @error('accepted_species')
                        <em class="err-t" style="font-style:normal">{{ $message }}</em>
                    @enderror
                    @error('accepted_species.*')
                        <em class="err-t" style="font-style:normal">{{ $message }}</em>
                    @enderror
                </div>
            </div>
        </div>

        <div>
            {{-- FOTO SAMPUL --}}
            <div class="card">
                <h3>Foto sampul</h3>
                <p class="sub">Foto ini tampil di katalog dan halaman bisnis.</p>

                <img
                    id="coverPreview"
                    class="cover-prev"
                    src="{{ $business->cover_url }}"
                    data-fallback="{{ $business->cover_fallback_url }}"
                    alt="Pratinjau foto sampul"
                    onerror="this.onerror=null;this.src=this.dataset.fallback"
                >

                <div class="form" style="margin-top:12px">
                    <label class="fld">
                        <span>Unggah foto sampul</span>
                        <input
                            type="file"
                            name="cover"
                            accept="image/jpeg,image/png,image/webp"
                            data-preview="coverPreview"
                        >
                    </label>

                    <p class="note">
                        JPG, PNG, atau WebP. Maksimal 3 MB.
                    </p>

                    @if ($business->has_cover_photo)
                        <label class="check">
                            <input type="checkbox" name="remove_cover" value="1">
                            Hapus foto sampul saat ini
                        </label>
                    @endif

                    @error('cover')
                        <p class="err-t">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- STATUS BISNIS --}}
            <div class="card mt" style="text-align:center">
                <p style="margin:0">
                    @if ($business->status)
                        <x-status status="Aktif" />
                    @else
                        <x-status status="Suspend" />
                    @endif
                </p>
                <p class="note">Status bisnis hanya dapat diubah oleh Admin.</p>
            </div>

            {{-- REKENING PEMBAYARAN --}}
            <div class="card mt">
                <h3>Rekening pembayaran</h3>
                <p class="sub">Tujuan transfer yang dilihat pelanggan.</p>

                <div class="form">
                    <x-field
                        name="bank_name"
                        label="Nama bank"
                        :value="$business->bank_name"
                        placeholder="Contoh: BCA"
                    />

                    <x-field
                        name="bank_account_number"
                        label="Nomor rekening"
                        :value="$business->bank_account_number"
                    />

                    <x-field
                        name="bank_account_name"
                        label="Atas nama"
                        :value="$business->bank_account_name"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="row mt" style="justify-content:flex-end">
        <button class="btn" type="submit">Simpan profil</button>
    </div>
</form>

<hr>

{{-- JADWAL OPERASIONAL & KAPASITAS SLOT --}}
<div class="card mt2">
    <h3>Jadwal operasional &amp; kapasitas slot</h3>
    <p class="sub">
        Atur jam buka, durasi slot, kapasitas per slot, serta jam istirahat setiap hari.
    </p>

    @php
        $days = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        $savedHours = $business->operatingHours->keyBy('day_of_week');
    @endphp

    <form method="POST" action="{{ route('owner.business.schedule.update') }}">
        @csrf
        @method('PUT')

        <div class="tw">
            <table>
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Tutup</th>
                        <th>Jam buka</th>
                        <th>Jam tutup</th>
                        <th>Durasi slot (mnt)</th>
                        <th>Kapasitas slot</th>
                        <th>Istirahat mulai</th>
                        <th>Istirahat selesai</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($days as $day => $label)
                        @php
                            $slot = $savedHours->get($day);

                            $closed = (bool) old(
                                "hours.$day.is_closed",
                                $slot?->is_closed ?? false
                            );
                        @endphp

                        <tr>
                            <td><strong>{{ $label }}</strong></td>

                            <td>
                                <label class="check">
                                    <input
                                        type="checkbox"
                                        name="hours[{{ $day }}][is_closed]"
                                        value="1"
                                        data-day-closed
                                        @checked($closed)
                                    >
                                    Tutup
                                </label>
                            </td>

                            <td>
                                <label class="fld" style="min-width:110px">
                                    <input
                                        type="time"
                                        name="hours[{{ $day }}][opens_at]"
                                        value="{{ old("hours.$day.opens_at", $slot?->opens_at ? substr($slot->opens_at, 0, 5) : '08:00') }}"
                                        @disabled($closed)
                                        required
                                    >
                                </label>
                            </td>

                            <td>
                                <label class="fld" style="min-width:110px">
                                    <input
                                        type="time"
                                        name="hours[{{ $day }}][closes_at]"
                                        value="{{ old("hours.$day.closes_at", $slot?->closes_at ? substr($slot->closes_at, 0, 5) : '17:00') }}"
                                        @disabled($closed)
                                        required
                                    >
                                </label>
                            </td>

                            <td>
                                <label class="fld" style="min-width:85px">
                                    <input
                                        type="number"
                                        name="hours[{{ $day }}][slot_duration_minutes]"
                                        min="15"
                                        max="480"
                                        value="{{ old("hours.$day.slot_duration_minutes", $slot?->slot_duration_minutes ?? 60) }}"
                                    >
                                </label>
                            </td>

                            <td>
                                <label class="fld" style="min-width:80px">
                                    <input
                                        type="number"
                                        name="hours[{{ $day }}][slot_capacity]"
                                        min="1"
                                        max="100"
                                        value="{{ old("hours.$day.slot_capacity", $slot?->slot_capacity ?? 5) }}"
                                    >
                                </label>
                            </td>

                            <td>
                                <label class="fld" style="min-width:110px">
                                    <input
                                        type="time"
                                        name="hours[{{ $day }}][break_starts_at]"
                                        value="{{ old("hours.$day.break_starts_at", $slot?->break_starts_at ? substr($slot->break_starts_at, 0, 5) : '') }}"
                                    >
                                </label>
                            </td>

                            <td>
                                <label class="fld" style="min-width:110px">
                                    <input
                                        type="time"
                                        name="hours[{{ $day }}][break_ends_at]"
                                        value="{{ old("hours.$day.break_ends_at", $slot?->break_ends_at ? substr($slot->break_ends_at, 0, 5) : '') }}"
                                    >
                                </label>
                            </td>
                        </tr>

                        @error("hours.$day.opens_at")
                            <tr><td colspan="8"><p class="err-t">{{ $message }}</p></td></tr>
                        @enderror
                        @error("hours.$day.closes_at")
                            <tr><td colspan="8"><p class="err-t">{{ $message }}</p></td></tr>
                        @enderror
                        @error("hours.$day.break_starts_at")
                            <tr><td colspan="8"><p class="err-t">{{ $message }}</p></td></tr>
                        @enderror
                        @error("hours.$day.break_ends_at")
                            <tr><td colspan="8"><p class="err-t">{{ $message }}</p></td></tr>
                        @enderror
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="note">
            Gunakan format 24 jam. Kosongkan jam istirahat jika bisnis beroperasi tanpa jeda istirahat.
        </p>

        <div class="row mt" style="justify-content:flex-end">
            <button class="btn" type="submit">Simpan jadwal</button>
        </div>
    </form>
</div>

{{-- HARI LIBUR KHUSUS --}}
<div class="card mt">
    <h3>Hari libur khusus</h3>
    <p class="sub">
        Tambahkan tanggal ketika bisnis tutup di luar jadwal mingguan.
    </p>

    <form
        method="POST"
        action="{{ route('owner.business.closures.store') }}"
        class="form"
    >
        @csrf

        <div class="form f2">
            <label class="fld">
                <span>Tanggal libur *</span>
                <input
                    type="date"
                    name="closed_date"
                    min="{{ now()->toDateString() }}"
                    value="{{ old('closed_date') }}"
                    required
                >
            </label>

            <label class="fld">
                <span>Alasan libur</span>
                <input
                    type="text"
                    name="reason"
                    maxlength="255"
                    value="{{ old('reason') }}"
                    placeholder="Contoh: Libur nasional"
                >
            </label>
        </div>

        @error('closed_date')
            <p class="err-t">{{ $message }}</p>
        @enderror

        @error('reason')
            <p class="err-t">{{ $message }}</p>
        @enderror

        <div class="row mt" style="justify-content:flex-end">
            <button class="btn" type="submit">Tambah hari libur</button>
        </div>
    </form>

    <hr>

    <h3>Daftar hari libur</h3>

    @forelse ($business->closures as $closure)
        <div class="item">
            <div>
                <strong>{{ $closure->closed_date->format('d M Y') }}</strong>

                <small class="mut" style="display:block">
                    {{ $closure->reason ?: 'Tidak ada keterangan' }}
                </small>
            </div>

            <form
                method="POST"
                action="{{ route('owner.business.closures.destroy', $closure->id) }}"
                onsubmit="return confirm('Hapus tanggal libur ini?')"
            >
                @csrf
                @method('DELETE')

                <button class="btn sm danger" type="submit">Hapus</button>
            </form>
        </div>
    @empty
        <x-empty
            icon="📅"
            title="Belum ada hari libur"
            text="Tanggal libur khusus yang ditambahkan akan muncul di sini."
        />
    @endforelse
</div>

<script>
document.querySelectorAll('[data-day-closed]').forEach(function (checkbox) {
    function updateTimeFields() {
        const row = checkbox.closest('tr');

        row.querySelectorAll('input[name$="[opens_at]"], input[name$="[closes_at]"]').forEach(function (input) {
            input.disabled = checkbox.checked;
            input.required = !checkbox.checked;
        });
    }

    checkbox.addEventListener('change', updateTimeFields);
    updateTimeFields();
});
</script>

@endsection