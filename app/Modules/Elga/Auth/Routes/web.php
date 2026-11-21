<?php

use App\Modules\Elga\Auth\Http\Controllers\AuthController;
use App\Modules\Elga\Auth\Http\Controllers\EmailVerificationController;
use Illuminate\Support\Facades\Route;

// Login dan registrasi
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');

    Route::get('/daftar/pelanggan', [AuthController::class, 'showRegisterCustomer'])
        ->name('register.customer');

    Route::post('/daftar/pelanggan', [AuthController::class, 'registerCustomer'])
        ->middleware('throttle:5,1')
        ->name('register.customer.store');

    Route::get('/daftar/mitra', [AuthController::class, 'showRegisterOwner'])
        ->name('register.owner');

    Route::post('/daftar/mitra', [AuthController::class, 'registerOwner'])
        ->middleware('throttle:5,1')
        ->name('register.owner.store');
});

// Logout
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Email verification
Route::middleware('auth')->group(function () {

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Profil pengguna
Route::middleware(['auth', 'role:customer,business_owner,admin'])->group(function () {

    Route::get('/profil', [
        \App\Modules\Elga\Auth\Http\Controllers\ProfileController::class,
        'edit'
    ])->name('profile.edit');

    Route::put('/profil', [
        \App\Modules\Elga\Auth\Http\Controllers\ProfileController::class,
        'update'
    ])->name('profile.update');

    Route::put('/profil/password', [
        \App\Modules\Elga\Auth\Http\Controllers\ProfileController::class,
        'password'
    ])->name('profile.password');

});
