# Redis Cart & Atomic Checkout

Implementasi Issue #10 untuk PawCare.

## Alur

1. Customer memilih layanan, hewan, tanggal, dan jam pada halaman booking.
2. Tombol **Tambah ke keranjang** menyimpan pilihan ke cache cart per customer.
3. Cart mengelompokkan layanan yang memiliki bisnis, hewan, tanggal, dan jam yang sama menjadi satu booking turunan.
4. Checkout menerima UUID `idempotency_key`. Key yang sama akan mengembalikan order yang sudah dibuat, bukan membuat order baru.
5. Dalam satu transaksi database, server mengunci baris bisnis dan layanan, memvalidasi ulang kepemilikan hewan, status layanan, harga, dan kapasitas slot, lalu membuat satu order induk beserta seluruh booking, booking item, payment, dan status log.
6. Jika satu grup gagal divalidasi, transaksi dibatalkan seluruhnya. Cart hanya dikosongkan setelah commit berhasil.
7. Harga yang berubah disinkronkan ke cart dan checkout dihentikan agar customer mengonfirmasi total terbaru.

## Mengaktifkan Redis

Cart memakai cache store Laravel yang dipilih oleh `CACHE_STORE`. Untuk memakai Redis:

```dotenv
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CACHE_DB=1
```

Pastikan server Redis aktif dan ekstensi PHP `redis` tersedia. Untuk pengembangan lokal tanpa Redis, `CACHE_STORE=file` tetap dapat digunakan; lock checkout tetap berjalan melalui cache file. Pengujian otomatis memakai cache `array`.

Setelah mengubah `.env`, jalankan:

```bash
php artisan config:clear
php artisan migrate
php artisan test --filter=AtomicCheckoutTest
```

## Database

- `orders`: order induk, idempotency key unik per customer, hash request, total, dan snapshot checkout.
- `bookings.order_id`: relasi nullable agar booking lama tetap kompatibel.
- `booking_items`: menyimpan snapshot harga per layanan seperti sebelumnya.

Tidak ada perubahan destruktif pada booking atau data pembayaran yang sudah ada.
