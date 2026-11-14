<?php

use App\Modules\Jeni\BookingCalendar\Http\Controllers\CustomerCalendarController;
use App\Modules\Jeni\BookingCalendar\Http\Controllers\OwnerPreorderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:customer'])
    ->prefix('customer/jeni')
    ->name('customer.jeni.')
    ->group(function (): void {
        Route::get('/kalender', [CustomerCalendarController::class, 'index'])
            ->name('calendar.index');
    });

Route::middleware(['auth', 'role:business_owner'])
    ->prefix('owner/jeni')
    ->name('owner.jeni.')
    ->group(function (): void {
        Route::get('/preorder', [OwnerPreorderController::class, 'edit'])
            ->name('preorder.edit');
        Route::put('/preorder', [OwnerPreorderController::class, 'update'])
            ->name('preorder.update');
    });
