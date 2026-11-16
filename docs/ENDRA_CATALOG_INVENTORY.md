# Endra: Catalog & Inventory Management — Issue #8

Target branch: `feat/catalog-inventory`

## Cakupan Fitur (SRS Bab 5.4, FR-07, FR-09, FR-10, FR-25, NFR-17, NFR-20 & ERD Bagian B)

- **Arsitektur Modular Monolith**: Modul `app/Modules/Endra/CatalogInventory` yang dimuat oleh `EndraServiceProvider` (`CatalogController`, `OwnerServiceController`, `CatalogInventoryService`, `Routes`, `Resources/views`).
- **Manajemen Layanan & Kapasitas Kuota Inventori (`FR-07`, `FR-09`, ERD Bagian B)**:
  - Penambahan kolom `capacity` (`unsignedSmallInteger`, default 5) pada tabel `services` beserta atribut `price_rupiah` (Integer Rupiah sesuai `NFR-17`) dan `is_available`.
  - Layanan yang sudah memiliki riwayat transaksi (`booking_items`) tidak pernah dihapus fisik, melainkan dinonaktifkan otomatis (`status = false`) sesuai `NFR-08`.
- **Siaran Katalog Realtime (`CatalogChanged` — SRS Bab 5.4, Bab 14, `NFR-20`)**:
  - Event broadcast `App\Events\CatalogChanged` disiarkan pada channel `catalog.updates` dan private channel bisnis setiap kali pemilik bisnis membuat, memperbarui, mengaktifkan/menonaktifkan, atau menghapus layanan.
- **Katalog Publik Reaktif & Cek Ketersediaan Slot/Inventori (`FR-10`, `FR-25`)**:
  - Pencarian kata kunci (nama bisnis, alamat, nama layanan), filter kategori layanan, filter spesies hewan, pengurutan (`name`, `price_asc`, `rating_desc`), serta endpoint JSON/UI `GET /bisnis/{business}/ketersediaan` (`catalog.availability`) untuk memeriksa sisa kuota slot per layanan dan tanggal.
