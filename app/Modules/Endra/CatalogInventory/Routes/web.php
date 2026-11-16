<?php

use App\Modules\Endra\CatalogInventory\Http\Controllers\CatalogController;
use App\Modules\Endra\CatalogInventory\Http\Controllers\OwnerServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/bisnis', [CatalogController::class, 'catalog'])->name('catalog');
Route::get('/bisnis/{business}', [CatalogController::class, 'showBusiness'])->name('catalog.show');
Route::get('/bisnis/{business}/ketersediaan', [CatalogController::class, 'availability'])->name('catalog.availability');

Route::middleware(['auth', 'role:business_owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function (): void {
        Route::get('/layanan', [OwnerServiceController::class, 'index'])
            ->name('services.index');

        Route::post('/layanan', [OwnerServiceController::class, 'store'])
            ->name('services.store');

        Route::get('/layanan/{service}/ubah', [OwnerServiceController::class, 'edit'])
            ->name('services.edit');

        Route::put('/layanan/{service}', [OwnerServiceController::class, 'update'])
            ->name('services.update');

        Route::patch('/layanan/{service}/toggle', [OwnerServiceController::class, 'toggle'])
            ->name('services.toggle');

        Route::delete('/layanan/{service}', [OwnerServiceController::class, 'destroy'])
            ->name('services.destroy');
    });
