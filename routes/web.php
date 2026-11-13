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

/* ---------------- Publik ---------------- */
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/bisnis', [HomeController::class, 'catalog'])->name('catalog');
Route::get('/bisnis/{business}', [HomeController::class, 'showBusiness'])->name('catalog.show');

/* ---------------- Webhook Payment Gateway ---------------- */
Route::post('/webhook/payment/midtrans', [PaymentWebhookController::class, 'midtrans'])
    ->name('webhooks.midtrans');

/* ---------------- Semua role yang sudah login ---------------- */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');


    // Akses bukti pembayaran privat tetap divalidasi oleh controller.
    Route::get('/pembayaran/{payment}/bukti', [PaymentProofController::class, 'show'])
        ->name('payments.proof');

    // Akses lampiran dokumen kesehatan hewan privat tetap divalidasi oleh controller.
    Route::get('/kesehatan-hewan/{record}/lampiran', [PetHealthAttachmentController::class, 'show'])
        ->name('pets.health.attachment');
});

/* ---------------- Customer ---------------- */
Route::middleware(['auth', 'role:customer'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/', [Customer\DashboardController::class, 'index'])
            ->name('dashboard');

        /*
         * Arsipkan, pulihkan, dan riwayat kesehatan hewan.
         * Diletakkan sebelum resource agar route khusus mudah dikenali.
         */
        Route::patch('/pets/{pet}/archive', [Customer\PetController::class, 'archive'])
            ->name('pets.archive');

        Route::patch('/pets/{pet}/restore', [Customer\PetController::class, 'restore'])
            ->name('pets.restore');

        Route::get('/pets/{pet}/kesehatan', [Customer\PetHealthController::class, 'show'])
            ->name('pets.health.show');

        Route::post('/pets/{pet}/kesehatan', [Customer\PetHealthController::class, 'store'])
            ->name('pets.health.store');

        Route::put('/pets/{pet}/kesehatan/{record}', [Customer\PetHealthController::class, 'update'])
            ->name('pets.health.update');

        Route::delete('/pets/{pet}/kesehatan/{record}', [Customer\PetHealthController::class, 'destroy'])
            ->name('pets.health.destroy');

        Route::patch('/pets/{pet}/kesehatan-izin', [Customer\PetHealthController::class, 'toggleShare'])
            ->name('pets.health.share');

        // CRUD data hewan.
        Route::resource('pets', Customer\PetController::class)
            ->except(['show']);

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
Route::middleware(['auth', 'role:business_owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/', [Owner\DashboardController::class, 'index'])
            ->name('dashboard');

        // Kalender Booking & Ketersediaan Slot.
        Route::get('/kalender', [Owner\CalendarController::class, 'index'])
            ->name('calendar.index');

        // Pengelolaan layanan.
        Route::get('/layanan', [Owner\ServiceController::class, 'index'])
            ->name('services.index');

        Route::post('/layanan', [Owner\ServiceController::class, 'store'])
            ->name('services.store');

        Route::get('/layanan/{service}/ubah', [Owner\ServiceController::class, 'edit'])
            ->name('services.edit');

        Route::put('/layanan/{service}', [Owner\ServiceController::class, 'update'])
            ->name('services.update');

        Route::patch('/layanan/{service}/toggle', [Owner\ServiceController::class, 'toggle'])
            ->name('services.toggle');

        Route::delete('/layanan/{service}', [Owner\ServiceController::class, 'destroy'])
            ->name('services.destroy');

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

        Route::post('/booking/{booking}/perawatan', [Owner\PetCareNoteController::class, 'store'])
            ->name('care-notes.store');

        Route::delete('/perawatan/{note}', [Owner\PetCareNoteController::class, 'destroy'])
            ->name('care-notes.destroy');

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

        Route::get('/pembayaran', [Admin\MonitorController::class, 'payments'])
            ->name('payments');

        Route::get('/laporan', [Admin\ReportController::class, 'index'])
            ->name('reports');

        Route::get('/laporan/ekspor', [Admin\ReportController::class, 'export'])
            ->name('reports.export');
    });