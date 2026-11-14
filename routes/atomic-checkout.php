<?php

use App\Http\Controllers\Customer\BookingCartController;
use App\Http\Controllers\Customer\OrderController;
use Illuminate\Support\Facades\Route;

// Route tambahan dimuat dari bootstrap/app.php dan memakai middleware web secara eksplisit.
Route::middleware(['web', 'auth', 'role:customer'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/keranjang-booking', [BookingCartController::class, 'index'])
            ->name('bookings.cart');

        Route::post('/keranjang-booking', [BookingCartController::class, 'store'])
            ->name('bookings.cart.add');

        Route::delete('/keranjang-booking/{group}', [BookingCartController::class, 'destroy'])
            ->where('group', '[a-f0-9]{24}')
            ->name('bookings.cart.remove');

        Route::post('/keranjang-booking/checkout', [BookingCartController::class, 'checkout'])
            ->name('bookings.cart.checkout');

        Route::get('/pesanan/{order}', [OrderController::class, 'show'])
            ->whereNumber('order')
            ->name('orders.show');
    });
