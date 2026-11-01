# 🐾 PawCare — Laravel

Sistem informasi layanan penitipan & perawatan hewan berbasis web, multi-klien (multi-business), dibangun dari mockup HTML, SRS, dan ERD PawCare.

> ⚠️ **Status validasi:** pemeriksaan sintaks PHP dan template Blade telah dilakukan. Suite tes fungsional tetap harus dijalankan pada PHP lokal yang memiliki ekstensi `pdo_sqlite`, `dom`, `mbstring`, dan `xmlwriter`; hasil tes lengkap tidak dapat disertifikasi dari lingkungan penyusunan paket ini. Lihat bagian 10 untuk verifikasi.

## 1. Fitur
- **Publik**: landing page, katalog bisnis (cari + filter jenis layanan), detail bisnis & layanan, login, registrasi pelanggan dan mitra bisnis.
- **Customer**: dashboard, profil & ganti password, CRUD data hewan, booking (pilih bisnis/hewan/layanan/jumlah/tanggal/jam/catatan), invoice unik, total dihitung server, unggah bukti pembayaran, status & histori status, batalkan booking (saat Menunggu), riwayat transaksi.
- **Business Owner**: dashboard, profil bisnis + rekening, CRUD layanan & harga, daftar & detail pelanggan, booking masuk, ubah status (tercatat di `booking_status_logs`), verifikasi/tolak bukti bayar (alasan wajib), transaksi, laporan dengan filter periode.
- **Admin**: dashboard platform, kelola pengguna (aktif/nonaktif), manajemen klien (detail, ubah, suspend/aktifkan bisnis), pantau layanan/booking/pembayaran, laporan sistem dengan filter periode.
- **Keamanan**: password di-hash (bcrypt), CSRF, escaping Blade, middleware `role`, query dibatasi per pemilik (data bisnis lain → 404), bukti pembayaran di disk privat dengan otorisasi, throttle login.

## 2. Kebutuhan
- PHP **8.2+** (Laravel 12), ekstensi: `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `tokenizer`, `xml`, `curl` (untuk MySQL tambahkan `pdo_mysql`).
- Composer 2.x. Node.js **tidak** diperlukan (CSS/JS lokal di `public/`).
- Font Poppins dimuat dari Google Fonts (opsional; jika offline memakai font sistem).

## 3. Instalasi di Windows
1. Ekstrak `PawCare-Laravel.zip` (misal ke `C:\PawCare-Laravel`).
2. Pasang PHP & Composer bila belum ada (bagian 4).
3. Klik dua kali **`setup.bat`** — memasang dependency, membuat `.env`, `APP_KEY`, database SQLite, migration + seeder (hanya jika database masih kosong; data tidak pernah dihapus otomatis).
4. Klik dua kali **`start-demo.bat`**, lalu buka **http://127.0.0.1:8000**.
5. Hentikan server dengan `Ctrl+C` di jendela terminal.

## 4. Memasang PHP & Composer
- **Cara mudah**: pasang [Laragon](https://laragon.org/) (membawa PHP & Composer), atau XAMPP (pilih PHP 8.2+).
- **Manual**: unduh PHP 8.2+ (Thread Safe, x64) dari windows.php.net → ekstrak ke `C:\php` → salin `php.ini-development` menjadi `php.ini` → hapus tanda `;` pada baris `extension=` yang disebut di bagian 2 → tambahkan `C:\php` ke PATH. Pasang Composer dari getcomposer.org/download. Buka terminal baru, cek `php -v` dan `composer -V`.

## 5. Akun demo (hanya lokal; password semua `password`)
| Role | Email |
|---|---|
| Admin | `admin@pawcare.test` |
| Business Owner (Happy Paws) | `owner@pawcare.test` |
| Business Owner (Meong & Guk) | `owner2@pawcare.test` |
| Business Owner (Griya Satwa) | `owner3@pawcare.test` |
| Customer | `customer@pawcare.test` |
| Customer lain | `budi@pawcare.test`, `sari@pawcare.test` |

**⚠️ Ganti/hapus semua akun dan password demo sebelum aplikasi dipublikasikan.** Halaman login menampilkan kotak akun demo hanya saat `APP_ENV=local`.

## 6. Perintah berguna (jalankan di folder project)
```
php artisan migrate                 # jalankan migration
php artisan migrate:fresh --seed    # RESET database + isi ulang data demo (menghapus semua data!)
php artisan db:seed                 # isi data demo (dilewati jika sudah ada)
php artisan route:list              # daftar route
php artisan test                    # jalankan test
type storage\logs\laravel.log       # lihat log error
```

## 7. Ganti SQLite → MySQL/MariaDB
Buat database kosong `pawcare`, lalu di `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pawcare
DB_USERNAME=root
DB_PASSWORD=
```
Jalankan `php artisan migrate --seed`.

## 8. Siapkan untuk GitHub
```
git init
git add .
git commit -m "PawCare Laravel"
```
`.env`, `vendor/`, dan file `.sqlite` sudah ada di `.gitignore`. Setelah `composer install` pertama, commit juga `composer.lock`.

## 9. Deployment (demo online)
Pilihan: hosting PHP (cPanel) atau VPS. Garis besar: unggah project, arahkan *document root* ke folder `public/`, `composer install --no-dev --optimize-autoloader`, salin `.env.example` → `.env`, atur `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, kredensial MySQL, lalu `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`, pastikan `storage/` dan `bootstrap/cache/` dapat ditulis. Jangan menjalankan seeder demo di produksi tanpa mengganti password.

## 10. Verifikasi pertama kali
Setelah `setup.bat`, jalankan: `composer validate`, `php artisan route:list`, `php artisan migrate:fresh --seed`, `php artisan test`. Test mencakup: login/redirect role, akses guest/role, isolasi data antar bisnis (owner A vs B, customer vs customer), validasi booking (layanan bisnis lain, tanggal lampau, total server), snapshot harga, status & log, verifikasi/tolak pembayaran, akses bukti pembayaran, CRUD hewan/layanan, rekap Admin, migration dari DB kosong, seeder.

## 11. Perbedaan dari ERD (disengaja, semuanya nullable/ber-default)
- `users.password` (bukan `password_hash`) — mengikuti konvensi autentikasi Laravel.
- Tambahan: `businesses.bank_*` (rekening transfer, ada di mockup), `services.category` & `services.unit` (jenis & satuan, ada di mockup), `booking_items.quantity` (jumlah hari/sesi, ada di mockup), `payments.reject_reason` (alasan tolak, diwajibkan spesifikasi), `booking_status_logs.note` (catatan status, ada di mockup).
- `payments.proof_url` → `proof_path` (path file di disk privat). `pets.age` disimpan dalam bulan.

## 12. Batasan
- UI Owner mengelola satu bisnis utama (database & query sudah mendukung banyak bisnis per owner).
- Tidak ada fitur lupa password, notifikasi email, backup otomatis (NFR-07; lakukan backup `database/database.sqlite` atau `mysqldump` secara berkala), maupun ekspor laporan.
- Tombol "Ekspor", chip "jenis hewan diterima", dan unggah logo bisnis pada mockup tidak diimplementasikan.
- Admin memantau layanan/booking/pembayaran (read-only) dan mengelola pengguna & bisnis.

## 13. Pembaruan visual: foto hewan, layanan, dan sampul bisnis
Tampilan beranda, kartu layanan, katalog, detail bisnis, kartu hewan, dan pilihan hewan saat booking sekarang memakai gambar.

- **Urutan gambar**: foto unggahan pengguna → foto lokal di `public/images/pawcare/` → ilustrasi fallback (`public/images/pawcare/fallback/*.svg`). Jika file gambar gagal dimuat di browser, `<x-photo>` otomatis memakai fallback.
- **Foto asli**: paket ini membawa **ilustrasi vektor**, bukan foto. Letakkan foto berlisensi sesuai tabel di `public/images/pawcare/README.md` (nama file harus persis), lalu refresh. Cek dengan `php artisan pawcare:images`. Catat sumber/lisensi di `public/images/pawcare/CREDITS.md`.
- **Unggah oleh pengguna**: pemilik bisnis → *Profil bisnis* → Foto sampul (maks 3 MB); pelanggan → *Tambah/Ubah hewan* → Foto hewan (maks 2 MB). Format JPG/PNG/WebP (SVG ditolak). Disimpan di `public/uploads/` (tanpa `storage:link`).
- **Database**: migration baru `2026_10_15_000001_add_photo_columns_to_businesses_and_pets` menambah kolom nullable `businesses.cover_photo` dan `pets.photo`. Data lama tidak berubah. **Jalankan `php artisan migrate` setelah mengekstrak.**

### Menjalankan dengan ServBay + VS Code (Windows)
1. Ekstrak `PawCare-Laravel-Visual-Update.zip`, buka folder hasil ekstrak di VS Code (*File → Open Folder*).
2. Di ServBay: aktifkan PHP 8.2+ (disarankan 8.3) dan pastikan ekstensi `pdo_sqlite`, `fileinfo`, `mbstring`, `openssl` aktif.
3. Terminal VS Code (terminal ServBay/PowerShell yang `php` dan `composer`-nya terbaca):
   ```
   composer install        # lewati bila folder vendor sudah ada dan berfungsi
   copy .env.example .env  # hanya jika .env belum ada
   php artisan key:generate  # hanya jika APP_KEY kosong
   php artisan migrate
   php artisan test          # opsional (butuh ekstensi sqlite)
   php artisan serve
   ```
   Buka http://127.0.0.1:8000. ZIP ini menyertakan `.env` lokal untuk memudahkan pengujian awal. Konfigurasi tersebut hanya untuk pengembangan/demo; buat APP_KEY baru dan atur ulang kredensial sebelum deployment atau penggunaan publik.
4. (Opsional) Lewat domain ServBay: *Websites → Add*, arahkan **Document Root ke folder `public`** project, pilih PHP yang sama. Gambar memakai URL relatif terhadap domain yang dibuka, jadi `APP_URL` tidak memengaruhi tampilan gambar.
5. Jika memakai MySQL/MariaDB ServBay, ubah `DB_*` di `.env` (lihat bagian 7), buat database `pawcare`, lalu `php artisan migrate --seed`.
6. Jika foto gagal tersimpan, pastikan folder `public/uploads` dapat ditulis.
