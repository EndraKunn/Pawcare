# Titah — Payment Gateway & Financial Settlement (Issue #11)

## Fitur
- Midtrans Sandbox Charge API: QRIS dinamis dan Virtual Account.
- Payment attempts tersimpan per percobaan, termasuk payload dan status provider.
- Verifikasi signature notification SHA-512, amount check, idempotency log, dan status transition.
- Settlement otomatis setelah webhook sah atau pembayaran manual diverifikasi.
- Double-entry ledger (gateway clearing, platform revenue, business payable).
- Withdrawal Owner: saldo tersedia, request, approval/reject Admin, dan pencatatan referensi transfer.
- Refund request ke Midtrans Sandbox dan pembalikan ledger ketika webhook refund diterima.
- Rekonsiliasi status lokal terhadap status Midtrans.

## Konfigurasi
Isi kredensial Sandbox dari dashboard Midtrans ke `.env`:
`MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_MERCHANT_ID`, `MIDTRANS_IS_PRODUCTION=false`.
`MIDTRANS_USE_REMOTE_SANDBOX=true`; `PAWCARE_PLATFORM_FEE_PERCENT=10`.
Atur URL notifikasi Midtrans ke `https://domain-anda/webhook/payment/midtrans` (gunakan tunnel HTTPS saat lokal).

## Alur
1. Customer membuat payment attempt QRIS/VA.
2. Midtrans mengirim webhook; server memvalidasi SHA-512 dan gross amount.
3. Webhook idempotent memperbarui payment dan membuat settlement/ledger satu kali.
4. Owner mengajukan withdrawal dari saldo bisnis.
5. Admin menyetujui/menolak. Setelah transfer bank manual dilakukan Admin mencatat reference dan menandai paid.
6. Admin dapat meminta refund; untuk Midtrans, status akhir ditentukan oleh provider webhook.

## Catatan
- Jangan memasukkan server key produksi ke Git.
- Settlement ledger adalah sub-ledger internal PawCare, bukan bukti dana telah dicairkan bank.
- Withdrawal pada tahap ini adalah proses payout manual dengan approval dan referensi transfer, bukan disbursement otomatis ke bank.
- Rekonsiliasi memeriksa maksimal 200 transaksi per run; mismatch harus ditinjau Admin.
