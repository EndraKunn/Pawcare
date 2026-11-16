# Endra: Pet & Health Management — Issue #7

Target branch: `feat/pet-health-records`

## Cakupan Fitur (SRS Bab 5.3, FR-05, FR-06, FR-13, FR-24 & ERD Bagian B/E)

- **Arsitektur Modular Monolith**: Modul `app/Modules/Endra/PetHealth` yang dimuat oleh `EndraServiceProvider` (`Controllers`, `Services`, `Routes`, `Resources/views`).
- **Manajemen Profil Hewan (`FR-05`)**:
  - CRUD data hewan milik pelanggan dengan dukungan foto (`PhotoStorage`), tanggal lahir (`birth_date`), berat badan (`weight`), dan perhitungan umur otomatis dalam bulan.
  - Pengarsipan (`archive`) dan pemulihan (`restore`) hewan yang sudah memiliki riwayat booking tanpa menghapus data historis.
- **Rekam Kesehatan & Lampiran Privat (`FR-06`)**:
  - Pencatatan vaksinasi, alergi, kondisi khusus, obat, dan catatan kesehatan beserta pengingat jadwal vaksinasi berikutnya.
  - Validasi MIME lampiran privat (`JPG`, `PNG`, `WebP`, `PDF`) pada disk privat dengan pencegahan path traversal dan header `X-Content-Type-Options: nosniff`.
- **Izin Berbagi Rekam Kesehatan Granular (`pet_health_sharing_permissions` — ERD Bagian E)**:
  - Pelanggan dapat mengatur izin berbagi global (`share_health_with_business`) maupun izin spesifik per catatan kesehatan dan per bisnis mitra (`PATCH /customer/pets/{pet}/kesehatan/{record}/izin`).
- **Catatan Hasil Perawatan Bisnis (`FR-13`) & Siaran Realtime (`CareNotePublished`)**:
  - Pemilik bisnis mencatat hasil perawatan pasca-layanan pada booking miliknya, menyiarkan event `CareNotePublished`, dan mencatat jejak audit (`audit_logs`).
