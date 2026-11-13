# Dua branch untuk Elga

**Branch A `feat/elga-auth-client-access`**: modul `Auth`, `ElgaServiceProvider`, `Shared/AuditTrail.php`, migration audit logs, Mailable, test registrasi, dan penambahan provider serta pemindahan route Auth/Profile. Setelah PR pertama masuk `main`, buat branch kedua dari `main` terbaru.

**Branch B `feat/elga-business-admin`**: modul `Business` dan `Admin`, migration `business_closures.is_active`, model BusinessClosure, slot availability nonaktif, dan bagian test owner/admin. Jangan membawa ulang perubahan Auth dari branch A. `ElgaServiceProvider` sudah melewati modul yang belum ada sehingga branch A bisa digabung lebih dahulu tanpa route Business/Admin. Setelah branch B masuk, pastikan semua route tersedia.

**Penting:** ZIP project lengkap berisi dua fitur sekaligus untuk uji lokal. Untuk GitHub pakai **dua file git format-patch** yang disediakan di folder `git-patches`, bukan mengunggah ZIP ke satu branch. Perubahan bersama (`routes/web.php`, `bootstrap/providers.php`, dan `SlotAvailabilityService.php`) telah dipisahkan menurut fitur.
