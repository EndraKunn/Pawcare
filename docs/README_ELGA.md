# PawCare — Implementasi Elga (dua fitur)

Implementasi Elga ini dibuat berdasarkan `EndraKunn/Pawcare` branch `main` yang bisa dibaca pada 13 November 2026, dan tidak menimpa fitur Endra/Jeni/Titah. ZIP hasil sekarang adalah **project PawCare lengkap**, berdasarkan source yang diberikan dan diverifikasi terhadap `EndraKunn/Pawcare` branch `main`. Perubahan Elga sudah diterapkan pada source yang sama. Untuk mengirim perubahan ke GitHub, tersedia dua patch commit pada folder `git-patches`.

## Fitur dan branch

1. `feat/elga-auth-client-access`: Login Laravel, register owner/customer, profil dan password, welcome email Mailtrap, audit pendaftaran, rate limit, role-based access. File di `app/Modules/Elga/Auth` dan `app/Mail`.
2. `feat/elga-business-admin`: Profil dan jadwal operasional, hari libur nonaktif tanpa delete, pengelolaan status pengguna/bisnis oleh admin, audit penting. File di `app/Modules/Elga/Business`, `Admin`, dan `Shared`.

Provider `ElgaServiceProvider` independen sehingga bisa digabung dengan modul Endra tanpa mengganti fondasi PawCare.

## Cara menjalankan versi lengkap

Backup database dan commit working tree. Setelah ekstrak ZIP lengkap, masuk ke folder `Pawcare-main`:

```bash
cd Pawcare-main
php artisan optimize:clear
php artisan migrate
php artisan route:list
php artisan test --filter=ElgaAuthFeatureTest
php artisan test --filter=ElgaBusinessAdminFeatureTest
php artisan test
```

Integrasi ini **memindahkan hanya deklarasi route lama** untuk Auth, Profile, Owner Business, Admin Users/Clients agar route modul tidak ganda. Ia juga menambahkan provider, `is_active` pada BusinessClosure, dan memperbarui `SlotAvailabilityService` supaya hari libur nonaktif tidak dianggap libur lagi. File controller global sebelumnya tidak dipakai lagi untuk route Auth/Business/Admin setelah route modul Elga terdaftar; route anggota lain tetap berada di `routes/web.php`.

## Mailtrap

Di `.env` lokal, isi host, port, username, password, enkripsi sesuai inbox Mailtrap. Set `MAIL_MAILER=smtp` dan `MAIL_FROM_ADDRESS=no-reply@pawcare.test` (atau alamat sender yang disetujui Mailtrap). `MAIL_MAILER=log` hanya mencatat email ke log lokal. Email yang digunakan untuk **login** harus sesuai data pada seeder, seperti `admin@pawcare.test`, `owner@pawcare.test`, `customer@pawcare.test`. Registrasi baru memakai email yang diisi pengguna.

## Aturan arsitektur

- **Multi-client, bukan multi-tenant**: satu aplikasi, banyak bisnis; query owner selalu dibatasi `owner_id`/`business_id`. Tidak memakai tenancy package.
- `models User/Business/BusinessClosure` tetap model bersama yang digunakan modul anggota lain.
- Route, controller, service, dan sebagian besar Blade view Elga berada di `app/Modules/Elga`.
- Status bisnis/user diubah, tidak dihapus. Hari libur memiliki `is_active` dan disimpan historinya.
- Audit tidak menyimpan password, token, nomor rekening, atau request body.
- Audit registrasi dan pembaruan status tercatat. Welcome email dikirim via Laravel Mail, bisa dites dengan Mailtrap.

## Verifikasi sebelum PR

Jalankan `php artisan route:list` dan pastikan nama lama tetap ada: `login`, `login.attempt`, `register.customer`, `register.owner`, `logout`, `profile.*`, `owner.business.*`, `admin.users.*`, `admin.clients.*`. Jika ada nama yang duplikat, hentikan merge dan rekonsiliasi dengan branch anggota lain.

Gunakan **dua PR** sesuai arahan dosen: pertama Auth + shared audit/provider dan kedua Business/Admin. Lihat `docs/BRANCH_SPLIT_ELGA.md` dan folder `git-patches`.

**Batasan validasi:** file PHP dapat diperiksa sintaks, tetapi pengujian Laravel runtime memerlukan vendor Composer dan database terkonfigurasi. Jangan klaim fitur telah lolos pengujian hingga test lokal berhasil.
