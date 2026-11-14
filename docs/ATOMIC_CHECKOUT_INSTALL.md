# PawCare — Patch `feat/atomic-checkout`

Paket ini berisi implementasi **Issue #10 — Redis Cart & Atomic Checkout** untuk repository `EndraKunn/Pawcare`.

## Isi fitur

- Cart booking per customer melalui cache Laravel; gunakan `CACHE_STORE=redis` untuk backend Redis.
- Pengelompokan layanan menjadi satu booking berdasarkan bisnis, hewan, tanggal, dan jam.
- Revalidasi bisnis, hewan, layanan aktif, harga, dan kapasitas slot saat checkout.
- Satu order induk dengan beberapa booking turunan.
- Snapshot order menyimpan identitas bisnis/hewan, jadwal, detail layanan, harga satuan, jumlah, dan subtotal.
- Transaksi database atomik, `lockForUpdate()` pada bisnis/hewan/layanan, dan cache lock untuk checkout/cart.
- Idempotency UUID dengan unique index per customer agar retry tidak membuat order baru.
- Tampilan keranjang, tombol tambah ke keranjang, dan halaman detail order.
- Feature tests untuk multi-booking, idempotency, perubahan harga, rollback slot penuh, dan isolasi customer.

## Cara memasang

1. Buka PowerShell pada folder repository PawCare.
2. Pastikan working tree bersih, lalu sinkronkan branch fitur dengan `main` terbaru:

   ```powershell
   git fetch origin
   git switch feat/atomic-checkout
   git pull --ff-only origin main
   ```

   Jika branch belum ada secara lokal:

   ```powershell
   git fetch origin
   git switch --track -c feat/atomic-checkout origin/feat/atomic-checkout
   git pull --ff-only origin main
   ```

3. Ekstrak isi ZIP ini ke **folder root repository** (folder yang berisi `artisan`, `app`, `bootstrap`, dan `routes`). Izinkan file yang sama ditimpa. File `.env` lokal tidak ada di ZIP dan tidak akan tertimpa.
4. Jalankan script upload:

   ```powershell
   powershell -ExecutionPolicy Bypass -File .\docs\UPLOAD_ATOMIC_CHECKOUT.ps1
   ```

   Atau jalankan `git add`, `git commit`, dan `git push` manual seperti di script tersebut.

## Redis

Untuk menggunakan Redis sungguhan, pastikan server Redis berjalan dan ekstensi PHP `redis` tersedia. Di `.env`, atur:

```dotenv
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CACHE_DB=1
```

Untuk demo lokal tanpa Redis, biarkan `CACHE_STORE=file`; implementasi cart dan lock tetap kompatibel dengan file cache. Automated tests memakai cache `array`.

## Migrasi dan pengujian

Setelah PHP/Composer tersedia:

```powershell
php artisan config:clear
php artisan migrate
php artisan test --filter=AtomicCheckoutTest
php artisan test
```

Laporan validasi paket ada di `docs/STATUS_PENGUJIAN.md`.
