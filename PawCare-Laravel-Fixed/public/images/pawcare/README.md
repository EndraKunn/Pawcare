# Aset gambar PawCare

Aplikasi mencari gambar dengan urutan: **foto unggahan pengguna → foto lokal di folder ini → ilustrasi fallback**.
Folder `fallback/` berisi ilustrasi vektor bawaan (BUKAN foto) agar halaman tidak pernah kosong.

## Cara memasang foto asli
Taruh file dengan nama PERSIS seperti di bawah (ekstensi boleh `.jpg`, `.jpeg`, `.png`, atau `.webp`).
Tidak perlu mengubah kode atau database; refresh browser (Ctrl+F5).

| File | Dipakai di | Ukuran disarankan |
|---|---|---|
| `hero/hero.jpg` | Beranda (foto utama) | 1200×900 |
| `services/penitipan.jpg` | Kartu layanan Penitipan | 800×520 |
| `services/grooming.jpg` | Kartu layanan Grooming | 800×520 |
| `services/perawatan.jpg` | Kartu layanan Perawatan | 800×520 |
| `businesses/cover-1.jpg` … `cover-5.jpg` | Sampul bisnis (dipilih otomatis: id bisnis mod 5 + 1) | 1600×600 |
| `businesses/business-{id}.jpg` | Sampul khusus untuk bisnis ber-id tertentu (mis. `business-1.jpg` = Happy Paws) | 1600×600 |
| `pets/anjing.jpg`, `kucing.jpg`, `kelinci.jpg`, `hamster.jpg`, `burung.jpg`, `reptil.jpg` | Foto bawaan hewan per jenis (dipakai jika pemilik belum mengunggah foto) | 600×600 (persegi) |

Pemilik bisnis dan pelanggan juga bisa mengunggah foto sendiri lewat **Profil bisnis** dan **form Data hewan**;
foto unggahan selalu diprioritaskan dan disimpan di `public/uploads/`.

Cek status slot: `php artisan pawcare:images`

## Lisensi (penting)
Gunakan hanya foto yang lisensinya mengizinkan penggunaan komersial/aplikasi web, misalnya dari
Unsplash, Pexels, atau Pixabay. Baca lisensi di halaman setiap foto, karena aturan dapat berubah,
dan catat sumbernya di `CREDITS.md`. Kompres foto (disarankan < 300 KB per file, format WebP/JPG) agar halaman tetap cepat.
