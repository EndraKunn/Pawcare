# Lanjutkan repository PawCare yang sudah ada — Elga

**Basis:** `https://github.com/EndraKunn/Pawcare`, branch `main` sebagaimana file `Pawcare-main.zip` yang diunggah pada 13 November 2026. Pemeriksaan Git blob pada `routes/web.php`, `AuthController`, `resources/views/owner/business.blade.php`, dan `composer.json` cocok dengan `main` saat pengecekan.

**JANGAN membuat repository baru.** ZIP lengkap untuk pengujian lokal, sedangkan dua patch siap diintegrasikan secara berurutan ke repo yang ada.

## 1. Clone project

```bash
git clone https://github.com/EndraKunn/Pawcare.git
cd Pawcare
git status
```

Pastikan working tree bersih. Jika repo sudah ada, lakukan `git checkout main && git pull origin main` terlebih dahulu. **Backup `.env` dan database.**

## 2. Branch Elga pertama (Auth)

```bash
git checkout -b feat/elga-auth-client-access
git am /path/hasil-ekstrak/git-patches/0001-*.patch
git status
git push -u origin feat/elga-auth-client-access
```

Buat PR `feat/elga-auth-client-access` → `main` dan merge setelah review/test.

## 3. Branch Elga kedua (Business & Admin)

```bash
git checkout main
git pull origin main
git checkout -b feat/elga-business-admin
git am /path/hasil-ekstrak/git-patches/0002-*.patch
git status
git push -u origin feat/elga-business-admin
```

Buat PR `feat/elga-business-admin` → `main` dan merge setelah review/test. Jangan push perubahan langsung ke `main`.

Jika Git melaporkan konflik pada `git am`, lakukan review manual bersama anggota; jangan jalankan `git am --skip` tanpa menyelesaikan file yang konflik.

## 4. Jalankan Laravel

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan route:clear
php artisan route:list
php artisan test --filter=ElgaAuthFeatureTest
php artisan test --filter=ElgaBusinessAdminFeatureTest
php artisan test
php artisan serve
```

`composer.lock` masih dari basis utama karena scope Elga tidak menambah package Composer. `config/mail.php` disertakan agar Mailtrap dapat bekerja via Laravel Mail. Set `MAIL_MAILER=log` untuk pengujian lokal, atau ganti ke `smtp` dan masukkan kredensial Mailtrap di `.env` (jangan commit `.env`).

## 5. Batas scope dan integrasi

- Multi-client, **bukan** multi-tenant: data bisnis terikat ke `businesses.owner_id`, bukan database per tenant.
- Rute yang dipertahankan: `login*`, `register.*`, `profile.*`, `owner.business.*`, `admin.users.*`, `admin.clients.*`.
- Halaman Blade lama milik tim tetap digunakan desainnya (disalin ke folder module view namespace).
- Tidak menghapus permanen akun, bisnis, atau hari libur; status disimpan untuk riwayat.
- Perubahan `SlotAvailabilityService` diperlukan agar hari libur nonaktif tidak lagi menutup jadwal booking — lakukan code review bersama anggota booking.
- **Scope Endra yang sebelumnya dikirim sebagai ZIP belum berada di `main` yang diberikan**, jadi paket ini meneruskan current `main` + pekerjaan Elga, bukan mengklaim semua branch tim sudah digabung.
- PHPUnit dan migration tidak dijalankan di environment pembuatan artefak ini karena dependensi `vendor`/Composer serta driver database test tidak tersedia; pemeriksaan PHP syntax dapat dilakukan.
