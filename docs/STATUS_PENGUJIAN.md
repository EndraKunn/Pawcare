# Status Pengujian — Atomic Checkout

## Pemeriksaan yang sudah dilakukan

- `php -l` berhasil untuk seluruh file PHP baru dan file PHP yang dimodifikasi dalam patch.
- Migration order induk dan foreign key booking turunan ditambahkan tanpa mengubah atau menghapus data booking lama.
- Feature tests ditambahkan untuk checkout multi-bisnis, idempotency, perubahan harga, kapasitas slot penuh dengan rollback atomik, dan isolasi order antar-customer.

## Belum dapat disertifikasi dari lingkungan penyusunan paket

- `php artisan migrate` dan `php artisan test` belum dijalankan karena paket patch tidak menyertakan `vendor/` dan koneksi PHP proyek lokal pengguna sebelumnya diblokir Device Guard.
- Pengujian integrasi Redis membutuhkan server Redis aktif dan ekstensi PHP `redis`.
- Pengujian `lockForUpdate()` lintas koneksi harus dilakukan pada MySQL/MariaDB; SQLite tidak menguji perilaku row lock yang sama.

Jalankan perintah migrasi dan test pada bagian README setelah PHP/Composer serta database tersedia. Jangan jalankan `migrate:fresh` pada database yang berisi data penting.
