@extends('layouts.dashboard')
@section('title', 'Kesehatan & Riwayat '.$pet->name)
@section('heading', $pet->emoji.' '.$pet->name.' — Kesehatan & Perawatan')
@section('subtitle', $pet->species.' · '.($pet->breed ?? 'Campuran').' · '.$pet->age_label.' · '.$pet->gender)
@section('actions')
  <a class="btn line" href="{{ route('customer.pets.edit', $pet->id) }}">✏️ Ubah profil hewan</a>
  <a class="btn line" href="{{ route('customer.pets.index') }}">← Data hewan</a>
@endsection

@section('content')
<div class="alert" style="margin-bottom:16px">
  🩺 <span>Catatan ini merupakan rekam informasi kesehatan yang dimasukkan pengguna untuk membantu perawatan hewan dan <b>bukan pengganti diagnosis atau pemeriksaan dokter hewan</b>.</span>
</div>

{{-- PENGATURAN PRIVASI & PENGINGAT VAKSINASI --}}
<div class="grid g2" style="margin-bottom:20px">
  <div class="card">
    <div class="row sp wrapf">
      <div>
        <h3>🔒 Privasi catatan kesehatan</h3>
        <p class="sub" style="margin:2px 0 0">
          {{ $pet->share_health_with_business
              ? 'Mitra bisnis yang menangani booking '.$pet->name.' diizinkan melihat ringkasan alergi & vaksinasi.'
              : 'Catatan kesehatan bersifat privat hanya untuk Anda (tidak ditampilkan kepada bisnis).' }}
        </p>
      </div>
      <form method="POST" action="{{ route('customer.pets.health.share', $pet->id) }}">
        @csrf @method('PATCH')
        <button class="btn sm line" type="submit">
          {{ $pet->share_health_with_business ? 'Sembunyikan dari bisnis' : 'Izinkan bisnis melihat' }}
        </button>
      </form>
    </div>
  </div>

  <div class="card gold">
    <h3>💉 Pengingat vaksinasi berikutnya</h3>
    @if ($upcomingVaccinations->isEmpty())
      <p class="sub" style="margin:4px 0 0">Belum ada jadwal vaksinasi mendatang yang tercatat.</p>
    @else
      @foreach ($upcomingVaccinations as $vac)
        <div class="row sp" style="margin-top:6px">
          <span><b>{{ $vac->title }}</b></span>
          <span class="pill wait">Jadwal: {{ $vac->next_due_date->translatedFormat('d M Y') }}</span>
        </div>
      @endforeach
    @endif
  </div>
</div>

<div class="grid g21">
  <div>
    {{-- DAFTAR REKAM KESEHATAN --}}
    <div class="card">
      <h3>📋 Rekam kesehatan {{ $pet->name }}</h3>
      <p class="sub">Vaksinasi, alergi, kondisi khusus, riwayat obat, dan catatan tambahan.</p>

      @if ($pet->healthRecords->isEmpty())
        <x-empty icon="🩺" title="Belum ada catatan kesehatan" text="Tambahkan riwayat vaksinasi, alergi, atau kebutuhan khusus hewan Anda melalui formulir di samping." />
      @else
        @foreach ($pet->healthRecords as $rec)
          <div class="item" style="flex-direction:column;align-items:stretch;gap:6px;padding:14px 0">
            <div class="row sp wrapf">
              <div>
                <span class="pill {{ $rec->record_type === 'allergy' ? 'bad' : ($rec->record_type === 'vaccination' ? 'conf' : 'gray') }}">{{ $rec->type_label }}</span>
                <strong style="margin-left:8px">{{ $rec->title }}</strong>
              </div>
              <div class="row" style="gap:8px">
                @if ($rec->recorded_date)
                  <small class="mut">Tgl: {{ $rec->recorded_date->translatedFormat('d M Y') }}</small>
                @endif
                @if ($rec->next_due_date)
                  <span class="pill wait" style="font-size:11px">Berikutnya: {{ $rec->next_due_date->translatedFormat('d M Y') }}</span>
                @endif
              </div>
            </div>

            @if ($rec->notes)
              <p class="sm" style="margin:2px 0 0">{{ $rec->notes }}</p>
            @endif

            <div class="row sp wrapf" style="margin-top:4px">
              <div>
                @if ($rec->attachment_path)
                  <a class="btn sm line" href="{{ route('pets.health.attachment', $rec->id) }}" target="_blank" rel="noopener">
                    📎 Lihat dokumen ({{ $rec->attachment_name ?: 'Lampiran' }})
                  </a>
                @endif
              </div>

              <form method="POST" action="{{ route('customer.pets.health.destroy', [$pet->id, $rec->id]) }}" onsubmit="return confirm('Hapus catatan kesehatan ini?')">
                @csrf @method('DELETE')
                <button class="btn sm danger" type="submit">Hapus</button>
              </form>
            </div>
          </div>
        @endforeach
      @endif
    </div>

    {{-- RIWAYAT PERAWATAN BERDASARKAN BOOKING SAH --}}
    <div class="card mt2">
      <h3>🛁 Riwayat layanan &amp; perawatan PawCare</h3>
      <p class="sub">Terhubung langsung dengan booking yang tercatat untuk {{ $pet->name }}.</p>

      @if ($pet->bookings->isEmpty())
        <x-empty icon="📅" title="Belum ada riwayat perawatan" text="Riwayat booking dan catatan hasil perawatan dari mitra bisnis akan tampil di sini." />
      @else
        @foreach ($pet->bookings as $bk)
          <div class="item" style="flex-direction:column;align-items:stretch;gap:6px;padding:14px 0">
            <div class="row sp wrapf">
              <div>
                <a class="gold-t b" href="{{ route('customer.bookings.show', $bk->id) }}">{{ $bk->invoice_number }}</a>
                <span class="sm" style="margin-left:6px">· {{ $bk->business->business_name ?? '-' }}</span>
              </div>
              <div class="row" style="gap:8px">
                <small class="mut">{{ $bk->schedule_label }}</small>
                <x-status :status="$bk->status" />
              </div>
            </div>

            <div class="sm mut">
              Layanan: {{ $bk->items->map(fn ($i) => ($i->service->name ?? 'Layanan').' ×'.$i->quantity)->implode(', ') }}
              @if ($bk->notes) · Catatan pelanggan: "{{ $bk->notes }}" @endif
            </div>

            @if ($bk->careNotes && $bk->careNotes->isNotEmpty())
              @foreach ($bk->careNotes as $cn)
                <div style="margin-top:6px;padding:10px 14px;border-left:3px solid var(--ok);background:rgba(62,207,142,.08);border-radius:8px">
                  <small class="b" style="color:var(--ok);display:block">Catatan Hasil Perawatan Bisnis · {{ $cn->created_at?->translatedFormat('d M Y, H:i') }}</small>
                  <p class="sm" style="margin:4px 0 0">{{ $cn->care_summary }}</p>
                  @if ($cn->recommendations)
                    <small class="gold-t" style="display:block;margin-top:4px">Saran lanjutan: {{ $cn->recommendations }}</small>
                  @endif
                </div>
              @endforeach
            @endif
          </div>
        @endforeach
      @endif
    </div>
  </div>

  {{-- FORM TAMBAH CATATAN KESEHATAN --}}
  <div>
    <div class="card">
      <h3>+ Tambah catatan kesehatan</h3>
      <p class="sub">Informasi yang belum diketahui boleh dikosongkan.</p>
      <form method="POST" action="{{ route('customer.pets.health.store', $pet->id) }}" class="form" enctype="multipart/form-data">
        @csrf
        <x-field name="record_type" label="Kategori catatan" type="select" :options="\App\Models\PetHealthRecord::TYPES" required />
        <x-field name="title" label="Nama vaksin / alergi / kondisi" placeholder="Contoh: Vaksin Rabies / Alergi Ayam" required />
        <div class="form f2">
          <x-field name="recorded_date" label="Tanggal tercatat (opsional)" type="date" />
          <x-field name="next_due_date" label="Jadwal berikutnya (opsional)" type="date" hint="Khusus vaksinasi / kontrol ulang" />
        </div>
        <x-field name="notes" label="Keterangan tambahan (opsional)" type="textarea" rows="3" placeholder="Dosis, reaksi alergi, atau instruksi khusus untuk groomer/penjaga" />
        <label class="fld">
          <span>Lampiran dokumen privat (opsional)</span>
          <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf">
          <em class="h" style="font-style:normal">JPG, PNG, WebP, atau PDF (maks. 2 MB). Disimpan di penyimpanan privat terenkripsi.</em>
          @error('attachment')<em class="err-t" style="font-style:normal">{{ $message }}</em>@enderror
        </label>
        <button class="btn block" type="submit">Simpan catatan kesehatan</button>
      </form>
    </div>
  </div>
</div>
@endsection
