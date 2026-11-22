<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Owner;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PetHealthAttachmentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/* ---------------- Webhook Payment Gateway ---------------- */
Route::post('/webhook/payment/midtrans', [PaymentWebhookController::class, 'midtrans'])
    ->middleware('throttle:60,1')
    ->name('webhooks.midtrans');

/* ---------------- Semua role yang sudah login ---------------- */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');


    // Akses bukti pembayaran privat tetap divalidasi oleh controller.
    Route::get('/pembayaran/{payment}/bukti', [PaymentProofController::class, 'show'])
        ->name('payments.proof');

});

/* ---------------- Customer ---------------- */
Route::middleware(['auth', 'verified', 'role:customer'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/', [Customer\DashboardController::class, 'index'])
            ->name('dashboard');

        // Notifikasi Customer.
        Route::get('/notifikasi', [
            Customer\NotificationController::class,
            'index',
        ])->name('notifications.index');

        Route::post('/notifikasi/baca-semua', [
            Customer\NotificationController::class,
            'readAll',
        ])->name('notifications.read-all');

        Route::get('/notifikasi/{notification}/buka', [
            Customer\NotificationController::class,
            'open',
        ])->name('notifications.open');

        // Booking Customer.
        Route::get('/booking', [Customer\BookingController::class, 'index'])
            ->name('bookings.index');

        Route::get('/booking/buat', [Customer\BookingController::class, 'create'])
            ->name('bookings.create');

        Route::post('/booking', [Customer\BookingController::class, 'store'])
            ->name('bookings.store');

        Route::get('/booking/{booking}', [Customer\BookingController::class, 'show'])
            ->name('bookings.show');

        Route::patch('/booking/{booking}/batal', [Customer\BookingController::class, 'cancel'])
            ->name('bookings.cancel');

        // Rating & Ulasan Pelanggan.
        Route::post('/booking/{booking}/ulasan', [Customer\ReviewController::class, 'store'])
            ->name('reviews.store');

        Route::put('/ulasan/{review}', [Customer\ReviewController::class, 'update'])
            ->name('reviews.update');

        Route::delete('/ulasan/{review}', [Customer\ReviewController::class, 'destroy'])
            ->name('reviews.destroy');

        // Pembayaran Customer (Manual Transfer & Payment Gateway Sandbox).
        Route::get('/booking/{booking}/bayar', [Customer\PaymentController::class, 'show'])
            ->name('bookings.pay');

        Route::post('/booking/{booking}/bayar', [Customer\PaymentController::class, 'store'])
            ->name('bookings.pay.store');

        Route::post('/booking/{booking}/bayar/gateway', [Customer\PaymentController::class, 'checkoutGateway'])
            ->name('bookings.pay.gateway');

        // Chat Pelanggan.
        Route::get('/pesan', [Customer\ChatController::class, 'index'])
            ->name('chat.index');

        Route::post('/pesan/mulai', [Customer\ChatController::class, 'start'])
            ->name('chat.start');

        Route::get('/pesan/{conversation}', [Customer\ChatController::class, 'show'])
            ->name('chat.show');

        Route::post('/pesan/{conversation}', [Customer\ChatController::class, 'send'])
            ->name('chat.send');

        // Riwayat transaksi Customer.
        Route::get('/transaksi', [Customer\TransactionController::class, 'index'])
            ->name('transactions');
    });

/* ---------------- Business Owner ---------------- */
Route::middleware(['auth', 'verified', 'role:business_owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/', [Owner\DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/care-board', [Owner\CareBoardController::class, 'index'])->name('care-board');

        // Kalender Booking & Ketersediaan Slot.
        Route::get('/kalender', [Owner\CalendarController::class, 'index'])
            ->name('calendar.index');

        // Data pelanggan bisnis.
        Route::get('/pelanggan', [Owner\CustomerController::class, 'index'])
            ->name('customers.index');

        Route::get('/pelanggan/{customer}', [Owner\CustomerController::class, 'show'])
            ->name('customers.show');

        // Booking masuk, status, dan catatan perawatan hewan.
        Route::get('/booking', [Owner\BookingController::class, 'index'])
            ->name('bookings.index');

        Route::get('/booking/{booking}', [Owner\BookingController::class, 'show'])
            ->name('bookings.show');

        Route::patch('/booking/{booking}/status', [Owner\BookingController::class, 'updateStatus'])
            ->name('bookings.status');


        // Ulasan Pelanggan (Owner).
        Route::get('/ulasan', [Owner\ReviewController::class, 'index'])
            ->name('reviews.index');

        Route::post('/ulasan/{review}/balas', [Owner\ReviewController::class, 'reply'])
            ->name('reviews.reply');

        // Chat Owner.
        Route::get('/pesan', [Owner\ChatController::class, 'index'])
            ->name('chat.index');

        Route::post('/booking/{booking}/pesan', [Owner\ChatController::class, 'startFromBooking'])
            ->name('chat.from-booking');

        Route::get('/pesan/{conversation}', [Owner\ChatController::class, 'show'])
            ->name('chat.show');

        Route::post('/pesan/{conversation}', [Owner\ChatController::class, 'send'])
            ->name('chat.send');

        // Pembayaran.
        Route::get('/pembayaran', [Owner\PaymentController::class, 'index'])
            ->name('payments.index');

        Route::get('/pembayaran/{payment}', [Owner\PaymentController::class, 'show'])
            ->name('payments.show');

        Route::patch('/pembayaran/{payment}/verifikasi', [Owner\PaymentController::class, 'verify'])
            ->name('payments.verify');

        Route::patch('/pembayaran/{payment}/tolak', [Owner\PaymentController::class, 'reject'])
            ->name('payments.reject');

        // Transaksi dan laporan bisnis.
        // Penarikan saldo bisnis (Withdrawal).
        Route::get('/penarikan', [Owner\WithdrawalController::class, 'index'])
            ->name('withdrawals.index');

        Route::post('/penarikan', [Owner\WithdrawalController::class, 'store'])
            ->name('withdrawals.store');

        Route::get('/transaksi', [Owner\TransactionController::class, 'index'])
            ->name('transactions');

        Route::get('/laporan', [Owner\ReportController::class, 'index'])
            ->name('reports');

        Route::get('/laporan/ekspor', [Owner\ReportController::class, 'export'])
            ->name('reports.export');
    });

/* ---------------- Admin ---------------- */
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])
            ->name('dashboard');

        // Monitoring platform.
        Route::get('/layanan', [Admin\MonitorController::class, 'services'])
            ->name('services');

        Route::get('/booking', [Admin\MonitorController::class, 'bookings'])
            ->name('bookings');

        // Keuangan Platform: Settlement, Withdrawal, Refund & Rekonsiliasi (Perlu Tindak Lanjut).
        Route::get('/keuangan', [Admin\PaymentFinanceController::class, 'index'])
            ->name('finance.index');

        Route::post('/keuangan/rekonsiliasi', [Admin\PaymentFinanceController::class, 'reconcile'])
            ->name('finance.reconcile');

        Route::patch('/keuangan/withdrawal/{withdrawal}/setujui', [Admin\PaymentFinanceController::class, 'approve'])
            ->name('finance.withdrawals.approve');

        Route::patch('/keuangan/withdrawal/{withdrawal}/tolak', [Admin\PaymentFinanceController::class, 'reject'])
            ->name('finance.withdrawals.reject');

        Route::patch('/keuangan/withdrawal/{withdrawal}/bayar', [Admin\PaymentFinanceController::class, 'paid'])
            ->name('finance.withdrawals.paid');

        Route::post('/keuangan/pembayaran/{payment}/refund', [Admin\PaymentFinanceController::class, 'refund'])
            ->name('finance.refunds.store');

        Route::patch('/keuangan/refund/{refund}/selesai', [Admin\PaymentFinanceController::class, 'completeManualRefund'])
            ->name('finance.refunds.complete');

        Route::get('/pembayaran', [Admin\MonitorController::class, 'payments'])
            ->name('payments');

        Route::get('/laporan', [Admin\ReportController::class, 'index'])
            ->name('reports');

        Route::get('/laporan/ekspor', [Admin\ReportController::class, 'export'])
            ->name('reports.export');
    });